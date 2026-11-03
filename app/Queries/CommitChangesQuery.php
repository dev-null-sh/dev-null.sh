<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

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
     * commit itself (its status, deletion, poll or link card), and which of its replies and quotes. Only replies
     * everyone could see, or the viewer's own, are named. A commit gone missing counts as changed, so the page reads
     * it again and finds out.
     *
     * @param  list<string>  $ids
     * @return array<string, CommitChange>
     */
    public function get(?User $viewer, array $ids, CarbonImmutable $since): array
    {
        $changed = Commit::withTrashed()
            ->whereKey($ids)
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
                        ->orWhereRaw('polls.opened_at + polls.duration * interval \'1 minute\' between ? and ?', [$since, now()])
                        ->orWhereExists(fn (Builder $votes): Builder => $votes->from('poll_votes')
                            ->whereColumn('poll_votes.poll_id', 'polls.id')
                            ->where('poll_votes.created_at', '>', $since)))))
            ->pluck('id');
        $existing = Commit::withTrashed()->whereKey($ids)->pluck('id');

        $replies = Commit::withTrashed()
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->whereIn('parent_id', $ids)->orWhereIn('quoted_commit_id', $ids))
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->where('commits.created_at', '>', $since)
                ->orWhere('commits.updated_at', '>', $since)
                ->orWhere('commits.deleted_at', '>', $since))
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->where('status', CommitStatus::Approved)
                ->when($viewer, fn (CommitBuilder $query, User $viewer): CommitBuilder => $query->orWhere('user_id', $viewer->id)))
            ->oldest()
            ->oldest('id')
            ->get(['id', 'parent_id', 'quoted_commit_id']);

        return collect($ids)->mapWithKeys(function (string $id) use ($changed, $existing, $replies): array {
            $children = array_values($replies
                ->filter(fn (Commit $reply): bool => $reply->parent_id === $id || $reply->quoted_commit_id === $id)
                ->map(fn (Commit $reply): string => $reply->id)
                ->all());

            return [$id => [
                'changed' => $changed->contains($id) || ! $existing->contains($id),
                'replies' => array_slice($children, 0, self::REPLIES),
                'truncated' => count($children) > self::REPLIES,
            ]];
        })->all();
    }
}
