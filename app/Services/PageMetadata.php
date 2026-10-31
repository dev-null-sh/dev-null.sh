<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\Organization;
use App\Models\User;
use App\Queries\MacAppReleaseQuery;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * @phpstan-type Image array{url: string, width: int, height: int, type: string, alt: string}
 * @phpstan-type Feed array{url: string, title: string}
 * @phpstan-type WebsiteMetadata array{type: string, title: string, description: string, url: string, robots: string, images: list<Image>, structuredData: array<string, mixed>}
 * @phpstan-type CommitMetadata array{type: string, title: string, description: string, url: string, robots: string, images: list<Image>, publishedAt: string, author: string, feed: Feed, structuredData: array<string, mixed>}
 * @phpstan-type UserMetadata array{type: string, title: string, description: string, url: string, robots: string, images: list<Image>, feed: Feed, handle: string, structuredData: array<string, mixed>}
 * @phpstan-type OrganizationMetadata array{type: string, title: string, description: string, url: string, robots: string, images: list<Image>, handle: string, structuredData: array<string, mixed>}
 *
 * @phpstan-import-type MacAppRelease from MacAppReleaseQuery
 */
final readonly class PageMetadata
{
    /**
     * Lets search engines show large image previews and full snippets of public pages.
     */
    private const string INDEXABLE = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    private const int TITLE_EXCERPT_LENGTH = 70;

    private const int DESCRIPTION_LENGTH = 200;

    private const int MAX_COMMENTS = 10;

    public function __construct(private Urls $urls)
    {
        //
    }

    /**
     * The link preview and structured data of the landing page.
     *
     * @return WebsiteMetadata
     */
    public function home(): array
    {
        $url = route('home');
        $description = 'A social network for developers. Sign in with GitHub. Commit messages to the log. Talk with other devs.';

        return [
            'type' => 'website',
            'title' => '/dev/null: a social network for developers',
            'description' => $description,
            'url' => $url,
            'robots' => self::INDEXABLE,
            'images' => [[
                'url' => url('/og.png'),
                'width' => OpenGraphCanvas::WIDTH,
                'height' => OpenGraphCanvas::HEIGHT,
                'type' => 'image/png',
                'alt' => '/dev/null: a social network for developers',
            ]],
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebSite',
                        '@id' => "{$url}#website",
                        'url' => $url,
                        'name' => '/dev/null',
                        'alternateName' => 'devnull',
                        'description' => $description,
                        'inLanguage' => str_replace('_', '-', app()->getLocale()),
                        'publisher' => ['@id' => "{$url}#organization"],
                    ],
                    [
                        '@type' => 'Organization',
                        '@id' => "{$url}#organization",
                        'name' => '/dev/null',
                        'url' => $url,
                        'logo' => url('/icon-512.png'),
                        'sameAs' => ['https://github.com/'.config()->string('services.github.repository')],
                    ],
                ],
            ],
        ];
    }

    /**
     * The link preview and structured data of a static page, such as the terms of service or the privacy policy.
     *
     * @return WebsiteMetadata
     */
    public function page(string $title, string $description, string $url): array
    {
        return [
            'type' => 'website',
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'robots' => self::INDEXABLE,
            'images' => [[
                'url' => url('/og.png'),
                'width' => OpenGraphCanvas::WIDTH,
                'height' => OpenGraphCanvas::HEIGHT,
                'type' => 'image/png',
                'alt' => $title,
            ]],
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                '@id' => $url,
                'url' => $url,
                'name' => $title,
                'description' => $description,
                'inLanguage' => str_replace('_', '-', app()->getLocale()),
                'isPartOf' => ['@id' => route('home').'#website'],
            ],
        ];
    }

    /**
     * The link preview and structured data of the apps page, with the Mac app described as software once it is published.
     *
     * @param  MacAppRelease|null  $release
     * @return WebsiteMetadata
     */
    public function apps(?array $release): array
    {
        $url = route('app.index');
        $page = $this->page(
            'Apps - /dev/null',
            '/dev/null for Mac: your remotes and orgs in a native app, with pull receipts, voice notes, reactions, and banners that never show what a message says.',
            $url,
        );

        return [
            ...$page,
            'images' => [[
                'url' => url('/images/apps/og.png'),
                'width' => OpenGraphCanvas::WIDTH,
                'height' => OpenGraphCanvas::HEIGHT,
                'type' => 'image/png',
                'alt' => '/dev/null for Mac, showing an org of developers talking in remotes',
            ]],
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    Arr::except($page['structuredData'], '@context'),
                    ...$release === null ? [] : [[
                        '@type' => 'SoftwareApplication',
                        'name' => '/dev/null for Mac',
                        'url' => $url,
                        'operatingSystem' => "macOS {$release['minimum_system_version']} or later",
                        'applicationCategory' => 'SocialNetworkingApplication',
                        'softwareVersion' => $release['version'],
                        'datePublished' => $release['released_at'],
                        'downloadUrl' => $release['download_url'],
                        'fileSize' => Number::fileSize($release['size'], precision: 1),
                        'image' => url('/images/apps/mac-icon-light.webp'),
                        'screenshot' => url('/images/apps/mac.webp'),
                        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                    ]],
                ],
            ],
        ];
    }

    /**
     * The link preview and structured data of a commit, from its public preview, stars count, images, and the replies shown under it.
     * The commit's own images are previewed first, with its drawn card as a fallback.
     *
     * @param  array<int, Commit>  $replies
     * @return CommitMetadata|null
     */
    public function commit(Commit $commit, array $replies = []): ?array
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

        $author = $parentUser instanceof User
            ? "{$user->nameWithHandle()} replying to {$parentUser->mention()} on /dev/null"
            : "{$user->nameWithHandle()} on /dev/null";

        $body = Str::squish($this->urls->withShortenedUrls($commit->body));
        $title = $author.': “'.Str::limit($body, self::TITLE_EXCERPT_LENGTH, '…', preserveWords: true).'”';
        $description = Str::limit($body, self::DESCRIPTION_LENGTH, '…', preserveWords: true);

        $images = [
            ...$this->commitImages($commit, $user),
            [
                'url' => route('commit-open-graph-image.show', ['user' => $user, 'commit' => $commit, 'v' => $commit->openGraphImageVersion()]),
                'width' => OpenGraphCanvas::WIDTH,
                'height' => OpenGraphCanvas::HEIGHT,
                'type' => 'image/png',
                'alt' => "{$user->nameWithHandle()}: {$description}",
            ],
        ];

        $comments = collect($replies)
            ->filter(fn (Commit $reply): bool => ! $reply->trashed() && $reply->status === CommitStatus::Approved && $reply->user instanceof User)
            ->take(self::MAX_COMMENTS)
            ->map(fn (Commit $reply): array => $this->comment($reply))
            ->values()
            ->all();

        $sharedContent = $this->sharedContent($commit);

        return [
            'type' => 'article',
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'robots' => self::INDEXABLE,
            'images' => $images,
            'publishedAt' => $commit->created_at->toIso8601String(),
            'author' => route('user.show', $user),
            'feed' => $this->feed($user),
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'SocialMediaPosting',
                '@id' => $url,
                'url' => $url,
                'mainEntityOfPage' => $url,
                'headline' => Str::limit($description, 110, '…', preserveWords: true),
                'text' => $commit->body,
                'datePublished' => $commit->created_at->toIso8601String(),
                'author' => $this->person($user),
                'image' => array_column($images, 'url'),
                'commentCount' => $commit->replies_count,
                'interactionStatistic' => [
                    $this->interactionCounter('LikeAction', $commit->stars_count),
                    $this->interactionCounter('CommentAction', $commit->replies_count),
                ],
                ...$parent instanceof Commit && $parentUser instanceof User ? [
                    'isPartOf' => route('commit.show', [$parentUser, $parent]),
                ] : [],
                ...$sharedContent !== null ? ['sharedContent' => $sharedContent] : [],
                ...$comments !== [] ? ['comment' => $comments] : [],
            ],
        ];
    }

    /**
     * The link preview and structured data of a profile, from its public counts.
     *
     * @return UserMetadata
     */
    public function user(User $user): array
    {
        $title = "{$user->nameWithHandle()} on /dev/null";

        return [
            'type' => 'profile',
            'title' => $title,
            'description' => $user->bio ?? "Read {$user->mention()}'s commits on /dev/null, a social network for developers.",
            'url' => route('user.show', $user),
            'robots' => self::INDEXABLE,
            'images' => [$this->profileImage($user, $title)],
            'feed' => $this->feed($user),
            'handle' => $user->handle,
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
                        "https://github.com/{$user->handle}",
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
     * The link preview and structured data of an organization's page, from its GitHub profile.
     *
     * @return OrganizationMetadata
     */
    public function organization(Organization $organization): array
    {
        $nameWithHandle = $organization->name === $organization->handle ? "@{$organization->handle}" : "{$organization->name} (@{$organization->handle})";
        $title = "{$nameWithHandle} on /dev/null";
        $url = route('user.show', $organization->handle);

        return [
            'type' => 'profile',
            'title' => $title,
            'description' => $organization->bio ?? "Read the commits that mention @{$organization->handle} on /dev/null, a social network for developers.",
            'url' => $url,
            'robots' => self::INDEXABLE,
            'images' => [[
                'url' => route('user-open-graph-image.show', ['profile' => $organization->handle, 'v' => $organization->openGraphImageVersion()]),
                'width' => OpenGraphCanvas::WIDTH,
                'height' => OpenGraphCanvas::HEIGHT,
                'type' => 'image/png',
                'alt' => $title,
            ]],
            'handle' => $organization->handle,
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@type' => 'ProfilePage',
                'url' => $url,
                'mainEntity' => [
                    '@type' => 'Organization',
                    'name' => $organization->name,
                    'alternateName' => "@{$organization->handle}",
                    'url' => $url,
                    ...$organization->bio !== null ? ['description' => $organization->bio] : [],
                    ...$organization->avatar_url !== null ? ['image' => $organization->avatar_url] : [],
                    'sameAs' => [
                        "https://github.com/{$organization->handle}",
                        ...filled($organization->website) ? [$organization->website] : [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<Image>
     */
    private function commitImages(Commit $commit, User $user): array
    {
        if (! $commit->relationLoaded('images') || ! $commit->imagesAreVisibleTo(null)) {
            return [];
        }

        $count = $commit->images->count();
        $images = [];

        foreach ($commit->images->values() as $index => $image) {
            $images[] = [
                'url' => $image->url(),
                'width' => $image->width,
                'height' => $image->height,
                'type' => 'image/webp',
                'alt' => $count === 1
                    ? "Image attached to the commit of {$user->nameWithHandle()}"
                    : 'Image '.($index + 1)." of {$count} attached to the commit of {$user->nameWithHandle()}",
            ];
        }

        return $images;
    }

    /**
     * The Atom feed of the user's commits.
     *
     * @return Feed
     */
    private function feed(User $user): array
    {
        return [
            'url' => route('user-feed.show', $user),
            'title' => "{$user->nameWithHandle()} on /dev/null",
        ];
    }

    /**
     * @return Image
     */
    private function profileImage(User $user, string $alt): array
    {
        return [
            'url' => route('user-open-graph-image.show', ['profile' => $user->handle, 'v' => $user->openGraphImageVersion()]),
            'width' => OpenGraphCanvas::WIDTH,
            'height' => OpenGraphCanvas::HEIGHT,
            'type' => 'image/png',
            'alt' => $alt,
        ];
    }

    /**
     * The approved commit it quotes, or else the link it previews.
     *
     * @return array<string, mixed>|null
     */
    private function sharedContent(Commit $commit): ?array
    {
        $quoted = $commit->relationLoaded('quotedCommit') ? $commit->quotedCommit : null;

        if ($quoted instanceof Commit && ! $quoted->trashed() && $quoted->status === CommitStatus::Approved && $quoted->user instanceof User) {
            return [
                '@type' => 'SocialMediaPosting',
                'url' => route('commit.show', [$quoted->user, $quoted]),
                'author' => $this->person($quoted->user),
            ];
        }

        $link = $commit->relationLoaded('linkPreview') ? $commit->linkPreview : null;

        if (! $link instanceof LinkPreview) {
            return null;
        }

        return [
            '@type' => 'WebPage',
            'url' => $link->url,
            ...filled($link->title) ? ['name' => $link->title] : [],
            ...filled($link->description) ? ['description' => $link->description] : [],
            ...filled($link->image_url) ? ['image' => $link->image_url] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function comment(Commit $reply): array
    {
        assert($reply->user instanceof User);

        return [
            '@type' => 'Comment',
            'url' => route('commit.show', [$reply->user, $reply]),
            'text' => $reply->body,
            'datePublished' => $reply->created_at->toIso8601String(),
            'author' => $this->person($reply->user),
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
            'alternateName' => $user->mention(),
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
