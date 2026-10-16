<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class PageMetadata
{
    public function __construct(private Urls $urls)
    {
        //
    }

    /**
     * The link preview and structured data of a commit, from its public preview and stars count.
     *
     * @return array{type: string, title: string, description: string, url: string, image: string, publishedAt: string, author: string, structuredData: array<string, mixed>}|null
     */
    public function commit(Commit $commit): ?array
    {
        $user = $commit->user;

        if ($commit->trashed() || $commit->status !== CommitStatus::Approved || ! $user instanceof User) {
            return null;
        }

        $parent = $commit->parent;
        $parentUser = $parent instanceof Commit && ! $parent->trashed() && $parent->status === CommitStatus::Approved
            ? $parent->user
            : null;
        $url = route('commit.show', [$user, $commit]);

        $title = $parentUser instanceof User
            ? "{$user->nameWithHandle()} replying to @{$parentUser->username} on /dev/null"
            : "{$user->nameWithHandle()} on /dev/null";

        $description = Str::limit(Str::squish($this->urls->withShortenedUrls($commit->body)), 200, '…', preserveWords: true);

        return [
            'type' => 'article',
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'image' => route('commit-open-graph-image.show', ['user' => $user, 'commit' => $commit, 'v' => $commit->openGraphImageVersion()]),
            'publishedAt' => $commit->created_at->toIso8601String(),
            'author' => route('user.show', $user),
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'SocialMediaPosting',
                '@id' => $url,
                'url' => $url,
                'headline' => Str::limit($description, 110, '…', preserveWords: true),
                'text' => $commit->body,
                'datePublished' => $commit->created_at->toIso8601String(),
                'author' => $this->person($user),
                'commentCount' => $commit->replies_count,
                'interactionStatistic' => [
                    $this->interactionCounter('LikeAction', $commit->stars_count),
                    $this->interactionCounter('CommentAction', $commit->replies_count),
                ],
                ...$parent instanceof Commit && $parentUser instanceof User ? [
                    'isPartOf' => route('commit.show', [$parentUser, $parent]),
                ] : [],
            ],
        ];
    }

    /**
     * The link preview and structured data of a profile, from its public counts.
     *
     * @return array{type: string, title: string, description: string, url: string, image: string, feed: string, username: string, structuredData: array<string, mixed>}
     */
    public function user(User $user): array
    {
        return [
            'type' => 'profile',
            'title' => "{$user->nameWithHandle()} on /dev/null",
            'description' => $user->bio ?? __("Read @:username's commits on /dev/null, a social network for developers.", ['username' => $user->username]),
            'url' => route('user.show', $user),
            'image' => route('user-open-graph-image.show', ['user' => $user, 'v' => $user->openGraphImageVersion()]),
            'feed' => route('user-feed.show', $user),
            'username' => $user->username,
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'url' => route('user.show', $user),
                'dateCreated' => $user->created_at->toIso8601String(),
                'mainEntity' => [
                    ...$this->person($user),
                    'identifier' => $user->id,
                    ...$user->bio !== null ? ['description' => $user->bio] : [],
                    ...$user->avatar_url !== null ? ['image' => $user->avatar_url] : [],
                    'sameAs' => [
                        "https://github.com/{$user->username}",
                        ...filled($user->website) ? [$user->website] : [],
                    ],
                    'interactionStatistic' => [
                        $this->interactionCounter('FollowAction', $user->followers_count),
                        $this->interactionCounter('WriteAction', $user->commits_count),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{'@type': string, name: string, alternateName: string, url: string}
     */
    private function person(User $user): array
    {
        return [
            '@type' => 'Person',
            'name' => $user->name,
            'alternateName' => "@{$user->username}",
            'url' => route('user.show', $user),
        ];
    }

    /**
     * @return array{'@type': string, interactionType: string, userInteractionCount: int}
     */
    private function interactionCounter(string $action, int $count): array
    {
        return [
            '@type' => 'InteractionCounter',
            'interactionType' => "https://schema.org/{$action}",
            'userInteractionCount' => $count,
        ];
    }
}
