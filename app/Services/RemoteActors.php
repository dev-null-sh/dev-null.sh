<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\CreateUserKeyPair;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class RemoteActors
{
    public function __construct(
        private PublicAddresses $publicAddresses,
        private HttpSignatures $signatures,
        private ActivityStreams $streams,
        private CreateUserKeyPair $keys,
    ) {
        //
    }

    /**
     * Fetches the actor document behind an actor or key URL, without following redirects, so every server that is
     * contacted went through the same address checks. Signed as the given user, so servers in authorized fetch mode
     * answer. Null when it cannot or must not be fetched.
     *
     * @return array<mixed>|null
     */
    public function find(string $url, User $signer): ?array
    {
        $actorUrl = $this->allowedUrl(Str::before($url, '#'));
        $address = $actorUrl instanceof Url ? $this->publicAddresses->of($actorUrl, $this->allowsPrivateAddresses()) : null;

        if ($address === null) {
            return null;
        }

        $signer = $this->keys->handle($signer);
        assert($signer->private_key !== null);

        try {
            $response = Http::withHeaders($this->signatures->signFetch($actorUrl->toAsciiString(), $this->streams->keyId($signer), $signer->private_key))
                ->accept(ActivityStreams::CONTENT_TYPE)
                ->connectTimeout(3)
                ->timeout(5)
                ->withoutRedirecting()
                ->withOptions(['curl' => $this->publicAddresses->pin($actorUrl, $address)])
                ->get($actorUrl->toAsciiString());
        } catch (ConnectionException) {
            return null;
        }

        $actor = $response->successful() ? $response->json() : null;

        return is_array($actor) && ($actor['id'] ?? null) === $actorUrl->toAsciiString() ? $actor : null;
    }

    /**
     * Federation only talks https, except to a Mastodon running locally for testing.
     */
    public function allowedUrl(string $url): ?Url
    {
        $parsed = Url::parse($url);

        return $parsed instanceof Url && ($parsed->getScheme() === 'https' || $this->allowsPrivateAddresses()) ? $parsed : null;
    }

    public function allowsPrivateAddresses(): bool
    {
        return config()->boolean('federation.allow_private_addresses');
    }
}
