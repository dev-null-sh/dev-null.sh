<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dto\ThreadContinuation;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\LinkPreview;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\User;
use App\Queries\GuildCommitsQuery;
use App\Queries\LinkCommitsQuery;
use App\Services\LinkPages;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

/**
 * People and commit cards as the web shows them, which is how their models are serialized, with each stream shown
 * saying when it runs out, from the same person it was read with: a page left open stops showing the stream then,
 * whatever it hears or can still read. Only streams the response shows are dated, and only as they were read.
 */
final class ShownStreams
{
    /**
     * @return array<mixed>
     */
    public static function profile(Profile $profile): array
    {
        return self::authored($profile->toArray(), $profile);
    }

    /**
     * @return array<mixed>|null
     */
    public static function user(?User $user): ?array
    {
        return $user instanceof User ? self::dated($user->toArray(), $user) : null;
    }

    /**
     * @param  iterable<array-key, User>  $users
     * @return list<array<mixed>>
     */
    public static function users(iterable $users): array
    {
        $shown = [];

        foreach ($users as $user) {
            $shown[] = self::dated($user->toArray(), $user);
        }

        return $shown;
    }

    /**
     * @return array<mixed>|null
     */
    public static function commit(?Commit $commit, bool $withGuildMentions = true): ?array
    {
        if (! $commit instanceof Commit) {
            return null;
        }

        $shown = $commit->toArray();

        if ($commit->trashed()) {
            return $shown;
        }

        $shown['guild_handle'] = $commit->relationLoaded('guild') ? $commit->guild?->handle : null;
        $shown['guild'] = $commit->relationLoaded('guild') && $commit->guild !== null ? [
            'handle' => $commit->guild->handle,
            'name' => $commit->guild->name,
            'emoji' => $commit->guild->emoji,
            'icon_url' => $commit->guild->iconUrl(),
        ] : null;

        if (is_array($shown['author'] ?? null) && $commit->relationLoaded('author') && $commit->author instanceof Profile) {
            $shown['author'] = self::authored($shown['author'], $commit->author);
        }

        if (is_array($shown['link_preview'] ?? null)) {
            $shown['link_topic'] = self::linkTopic($commit);
        }

        if (is_array($shown['quoted_commit'] ?? null) && $commit->relationLoaded('quotedCommit')) {
            $shown['quoted_commit'] = self::commit($commit->quotedCommit, false);
        }

        $preview = $commit->hasAttribute('reply_preview') ? $commit->getAttribute('reply_preview') : null;

        if (is_array($preview) && $preview['data'] instanceof Collection) {
            $shown['reply_preview'] = [...$preview, 'data' => self::commits($preview['data'], false)];
        }

        return $withGuildMentions ? ShownGuildMentions::of($shown, request()->user()) : $shown;
    }

    /**
     * @param  iterable<array-key, Commit>  $commits
     * @return list<array<mixed>>
     */
    public static function commits(iterable $commits, bool $withGuildMentions = true): array
    {
        $shown = [];

        foreach ($commits as $commit) {
            $shown[] = self::commit($commit, false);
        }

        $shown = array_values(array_filter($shown));

        return $withGuildMentions ? ShownGuildMentions::many($shown, request()->user()) : $shown;
    }

    /**
     * The page of commit cards, paginated as before.
     *
     * @param  CursorPaginator<int, Commit>  $page
     * @return CursorPaginator<int, ShownCommitResource>
     */
    public static function page(CursorPaginator $page, ?User $joinedGuildsFor = null): CursorPaginator
    {
        $guilds = $joinedGuildsFor instanceof User ? resolve(GuildCommitsQuery::class)->joinedFor($page->items(), $joinedGuildsFor) : [];
        $cards = self::commits($page->items());
        $shown = clone $page;
        $shown->setCollection($page->getCollection()->values()->map(fn (Commit $commit, int $index): ShownCommitResource => new ShownCommitResource($commit, $guilds[$commit->id] ?? null, $cards[$index])));

        return $shown;
    }

    /**
     * @param  CursorPaginator<int, Notification>  $page
     * @return CursorPaginator<int, ShownNotificationResource>
     */
    public static function notifications(CursorPaginator $page): CursorPaginator
    {
        $cards = ShownGuildMentions::many($page->getCollection()->map(fn (Notification $notification): array => new ShownNotificationResource($notification)->toArray())->all(), request()->user());
        $shown = clone $page;
        $shown->setCollection($page->getCollection()->values()->map(function (Notification $notification, int $index) use ($cards): ShownNotificationResource {
            $resource = new ShownNotificationResource($notification);
            $resource->shown = $cards[$index];

            return $resource;
        }));

        return $shown;
    }

    /**
     * Reports with the commit each is about, paginated as before.
     *
     * @param  CursorPaginator<int, CommitReport>  $reports
     * @return CursorPaginator<int, array<mixed>>
     */
    public static function reports(CursorPaginator $reports): CursorPaginator
    {
        $shown = clone $reports;
        $shown->setCollection(collect(ShownGuildMentions::many($reports->getCollection()->map(fn (CommitReport $report): array => $report->relationLoaded('commit')
            ? [...$report->toArray(), 'commit' => self::commit($report->commit, false)]
            : $report->toArray())->all(), request()->user())));

        return $shown;
    }

    /**
     * @return array{data: list<array<mixed>>, position: int, length: int, is_capped: bool, next_id: string|null}
     */
    public static function continuation(ThreadContinuation $continuation): array
    {
        return [...$continuation->toArray(), 'data' => self::commits($continuation->commits)];
    }

    /**
     * Who else the viewer may see talk about the commit's link, once enough people do, so its card leads to the link's
     * page. Null when the card was loaded without them.
     *
     * @return array{url: string, others: int, authors: list<array<mixed>>}|null
     */
    private static function linkTopic(Commit $commit): ?array
    {
        $link = $commit->linkPreview;

        if (! $link instanceof LinkPreview || ! $link->hasAttribute('topic_people_count')) {
            return null;
        }

        $people = $link->getAttribute('topic_people_count');
        $commits = $link->getAttribute('topic_commits_count');

        if (! is_int($people) || ! is_int($commits) || $people < LinkCommitsQuery::MIN_PEOPLE) {
            return null;
        }

        $url = resolve(LinkPages::class)->url($link->url);

        return $url === null ? null : [
            'url' => $url,
            'others' => $commits - 1,
            'authors' => array_values($link->commits
                ->reject(fn (Commit $other): bool => $other->id === $commit->id || $other->author_id === $commit->author_id)
                ->unique('author_id')
                ->take(3)
                ->map(fn (Commit $other): array => $other->author instanceof Profile ? $other->author->toArray() : [])
                ->filter()
                ->all()),
        ];
    }

    /**
     * @param  array<mixed>  $shown
     * @return array<mixed>
     */
    private static function authored(array $shown, Profile $author): array
    {
        return $author->relationLoaded('user') && $author->user instanceof User ? self::dated($shown, $author->user) : $shown;
    }

    /**
     * @param  array<mixed>  $shown
     * @return array<mixed>
     */
    private static function dated(array $shown, User $user): array
    {
        return array_key_exists('live', $shown) ? [...$shown, 'live' => LiveStreamResource::of($user)] : $shown;
    }
}
