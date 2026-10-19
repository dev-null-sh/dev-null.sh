<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Uri\WhatWg\Url;

final readonly class ActivityStreams
{
    public const string CONTENT_TYPE = 'application/activity+json';

    private const string CONTEXT = 'https://www.w3.org/ns/activitystreams';

    private const string PUBLIC = 'https://www.w3.org/ns/activitystreams#Public';

    public function host(): string
    {
        $url = Url::parse(config()->string('app.url'));
        assert($url instanceof Url);

        return $url->getAsciiHost() ?? '';
    }

    public function handle(User $user): string
    {
        return $user->username.'@'.$this->host();
    }

    public function actorUrl(User $user): string
    {
        return route('actor.show', $user);
    }

    public function keyId(User $user): string
    {
        return $this->actorUrl($user).'#main-key';
    }

    /**
     * @return array<string, mixed>
     */
    public function webFinger(User $user): array
    {
        return [
            'subject' => 'acct:'.$this->handle($user),
            'aliases' => [route('user.show', $user), $this->actorUrl($user)],
            'links' => [
                ['rel' => 'self', 'type' => self::CONTENT_TYPE, 'href' => $this->actorUrl($user)],
                ['rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => route('user.show', $user)],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function actor(User $user): array
    {
        return array_filter([
            '@context' => [self::CONTEXT, 'https://w3id.org/security/v1'],
            'id' => $this->actorUrl($user),
            'type' => 'Person',
            'preferredUsername' => $user->username,
            'name' => $user->name ?? $user->username,
            'summary' => $user->bio === null ? null : $this->html($user->bio),
            'url' => route('user.show', $user),
            'icon' => $user->avatar_url === null ? null : ['type' => 'Image', 'url' => $user->avatar_url],
            'inbox' => route('actor-inbox.store', $user),
            'outbox' => route('actor-outbox.index', $user),
            'followers' => route('actor-followers.index', $user),
            'manuallyApprovesFollowers' => false,
            'discoverable' => true,
            'published' => $user->created_at->toIso8601ZuluString(),
            'publicKey' => [
                'id' => $this->keyId($user),
                'owner' => $this->actorUrl($user),
                'publicKeyPem' => $user->public_key,
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function collection(string $id, int $totalItems): array
    {
        return [
            '@context' => self::CONTEXT,
            'id' => $id,
            'type' => 'OrderedCollection',
            'totalItems' => $totalItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function create(Commit $commit): array
    {
        $user = $commit->user;
        assert($user instanceof User);

        $note = $this->note($commit);

        return [
            '@context' => self::CONTEXT,
            'id' => $this->noteUrl($commit, $user).'#create',
            'type' => 'Create',
            'actor' => $this->actorUrl($user),
            'published' => $note['published'],
            'to' => $note['to'],
            'cc' => $note['cc'],
            'object' => $note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(Commit $commit): array
    {
        $user = $commit->user;
        assert($user instanceof User);

        return [
            '@context' => self::CONTEXT,
            'id' => $this->noteUrl($commit, $user).'#delete',
            'type' => 'Delete',
            'actor' => $this->actorUrl($user),
            'to' => [self::PUBLIC],
            'object' => ['id' => $this->noteUrl($commit, $user), 'type' => 'Tombstone'],
        ];
    }

    /**
     * @param  array<mixed>  $follow
     * @return array<string, mixed>
     */
    public function accept(User $user, array $follow): array
    {
        return [
            '@context' => self::CONTEXT,
            'id' => $this->actorUrl($user).'#accepts/'.hash('sha256', json_encode($follow, JSON_THROW_ON_ERROR)),
            'type' => 'Accept',
            'actor' => $this->actorUrl($user),
            'object' => $follow,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function note(Commit $commit): array
    {
        $user = $commit->user;
        assert($user instanceof User);

        return [
            'id' => $this->noteUrl($commit, $user),
            'type' => 'Note',
            'attributedTo' => $this->actorUrl($user),
            'content' => $this->html($commit->body),
            'url' => $this->noteUrl($commit, $user),
            'published' => $commit->created_at->toIso8601ZuluString(),
            'to' => [self::PUBLIC],
            'cc' => [route('actor-followers.index', $user)],
            'attachment' => $commit->images->map(fn (CommitImage $image): array => array_filter([
                'type' => 'Image',
                'mediaType' => 'image/webp',
                'url' => $image->url(),
                'name' => $image->description,
                'width' => $image->width,
                'height' => $image->height,
            ], fn (mixed $value): bool => $value !== null))->values()->all(),
        ];
    }

    private function noteUrl(Commit $commit, User $user): string
    {
        return route('commit.show', [$user, $commit]);
    }

    private function html(string $text): string
    {
        return '<p>'.nl2br(e($text), false).'</p>';
    }
}
