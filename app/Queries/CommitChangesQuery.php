<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type CommitChange array{changed: bool, replies: list<string>, truncated: bool}
 */
final readonly class CommitChangesQuery
{
    /**
     * The most changed replies named for one commit: a thread with more reads all it loaded again instead.
     */
    public const int REPLIES = 50;

    /**
     * What changed about each commit since a page last read it, for a page that may have missed being told: the
     * commit itself (its status, deletion, poll or link card), and which of its replies and quotes. Only commits the
     * viewer can see are checked, tombstones included, and a commit they can't see answers like one that doesn't
     * exist: as changed, so the page reads it again and finds out. Only replies the viewer could see in the thread
     * are named; a reply that may have been shown and was hidden since asks the page to read again what it loaded,
     * without naming it.
     *
     * @param  list<string>  $ids
     * @return array<string, CommitChange>
     */
    public function get(?User $viewer, array $ids, CarbonImmutable $since): array
    {
        /** @var list<string> $visible */
        $visible = Commit::withTrashed()->whereKey($ids)->visibleTo($viewer)->pluck('id')->all();

        $changed = Commit::withTrashed()
            ->whereKey($visible)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->where('commits.updated_at', '>', $since)
                ->orWhere('commits.deleted_at', '>', $since)
                ->orWhereExists(fn (Builder $preview): Builder => $preview->from('link_previews')
                    ->whereColumn('link_previews.id', 'commits.link_preview_id')
                    ->where('link_previews.fetched_at', '>', $since))
                ->orWhereExists(fn (Builder $poll): Builder => $poll->from('polls')
                    ->whereColumn('polls.commit_id', 'commits.id')
                    ->where(fn (Builder $poll): Builder => $poll
                        ->where('polls.opened_at', '>', $since)
                        ->orWhereRaw("polls.opened_at + polls.duration * interval '1 minute' between ? and ?", [$since, now()])
                        ->orWhereExists(fn (Builder $votes): Builder => $votes->from('poll_votes')
                            ->whereColumn('poll_votes.poll_id', 'polls.id')
                            ->where('poll_votes.created_at', '>', $since)))))
            ->pluck('id')
            ->all();

        $replies = $this->changedReplies($viewer, $visible, $since, 'parent_id')
            ->concat($this->changedReplies($viewer, $visible, $since, 'quoted_commit_id'))
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->groupBy('of');
        $hidden = $this->hiddenSince($viewer, $visible, $since, 'parent_id') + $this->hiddenSince($viewer, $visible, $since, 'quoted_commit_id');

        return collect($ids)->mapWithKeys(function (string $id) use ($visible, $changed, $replies, $hidden): array {
            if (! in_array($id, $visible, true)) {
                return [$id => ['changed' => true, 'replies' => [], 'truncated' => false]];
            }

            /** @var list<string> $children */
            $children = $replies->get($id, collect())->pluck('id')->values()->all();

            return [$id => [
                'changed' => in_array($id, $changed, true),
                'replies' => array_slice($children, 0, self::REPLIES),
                'truncated' => count($children) > self::REPLIES || isset($hidden[$id]),
            ]];
        })->all();
    }

    /**
     * The replies or quotes, by the given key, the viewer could see in the thread that changed since: at most one more
     * than are named for each commit, ranked by the database, so a long thread isn't read whole to name a few.
     *
     * @param  list<string>  $ids
     * @param  'parent_id'|'quoted_commit_id'  $key
     * @return Collection<int, object{id: string, of: string, created_at: string}>
     */
    private function changedReplies(?User $viewer, array $ids, CarbonImmutable $since, string $key): Collection
    {
        $ranked = Commit::withTrashed()
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->whereIn("commits.{$key}", $ids)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->where('commits.created_at', '>', $since)
                ->orWhere('commits.updated_at', '>', $since)
                ->orWhere('commits.deleted_at', '>', $since))
            ->toBase()
            ->select(['commits.id', "commits.{$key} as of", 'commits.created_at'])
            ->selectRaw("row_number() over (partition by commits.{$key} order by commits.created_at, commits.id) as position");

        /** @var Collection<int, object{id: string, of: string, created_at: string}> $replies */
        $replies = DB::query()->fromSub($ranked, 'ranked')->where('position', '<=', self::REPLIES + 1)->get(['id', 'of', 'created_at']);

        return $replies;
    }

    /**
     * The commits, by the given key, with a reply or quote that existed when the page read them, changed since, and
     * that the viewer can't see now: it may have been shown, and only reading the thread again can take it off.
     *
     * @param  list<string>  $ids
     * @param  'parent_id'|'quoted_commit_id'  $key
     * @return array<string, true>
     */
    private function hiddenSince(?User $viewer, array $ids, CarbonImmutable $since, string $key): array
    {
        $older = fn (): CommitBuilder => Commit::withTrashed()
            ->whereIn("commits.{$key}", $ids)
            ->where('commits.created_at', '<=', $since)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->where('commits.updated_at', '>', $since)
                ->orWhere('commits.deleted_at', '>', $since));
        $count = fn (CommitBuilder $query): array => $query->toBase()
            ->groupBy("commits.{$key}")
            ->selectRaw("commits.{$key} as of, count(*) as changes")
            ->pluck('changes', 'of')
            ->map(fn (mixed $count): int => is_numeric($count) ? (int) $count : 0)
            ->all();

        $seen = $count($older()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer));
        $hidden = [];

        foreach ($count($older()) as $id => $changes) {
            if ($changes > ($seen[$id] ?? 0)) {
                $hidden[(string) $id] = true;
            }
        }

        return $hidden;
    }
}
