<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dto\ThreadContinuation;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\Profile;
use App\Models\User;
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
    public static function commit(?Commit $commit): ?array
    {
        if (! $commit instanceof Commit) {
            return null;
        }

        $shown = $commit->toArray();

        if ($commit->trashed()) {
            return $shown;
        }

        if (is_array($shown['author'] ?? null) && $commit->relationLoaded('author') && $commit->author instanceof Profile) {
            $shown['author'] = self::authored($shown['author'], $commit->author);
        }

        if (is_array($shown['quoted_commit'] ?? null) && $commit->relationLoaded('quotedCommit')) {
            $shown['quoted_commit'] = self::commit($commit->quotedCommit);
        }

        $preview = $commit->hasAttribute('reply_preview') ? $commit->getAttribute('reply_preview') : null;

        if (is_array($preview) && $preview['data'] instanceof Collection) {
            $shown['reply_preview'] = [...$preview, 'data' => self::commits($preview['data'])];
        }

        return $shown;
    }

    /**
     * @param  iterable<array-key, Commit>  $commits
     * @return list<array<mixed>>
     */
    public static function commits(iterable $commits): array
    {
        $shown = [];

        foreach ($commits as $commit) {
            $shown[] = self::commit($commit);
        }

        return array_values(array_filter($shown));
    }

    /**
     * The page of commit cards, paginated as before.
     *
     * @param  CursorPaginator<int, Commit>  $page
     * @return CursorPaginator<int, array<mixed>|null>
     */
    public static function page(CursorPaginator $page): CursorPaginator
    {
        $shown = clone $page;
        $shown->setCollection($page->getCollection()->map(fn (Commit $commit): ?array => self::commit($commit)));

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
        $shown->setCollection($reports->getCollection()->map(fn (CommitReport $report): array => $report->relationLoaded('commit')
            ? [...$report->toArray(), 'commit' => self::commit($report->commit)]
            : $report->toArray()));

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
