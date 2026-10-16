<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Services\TheAlgorithm;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommitHotScores
{
    public function __construct(private TheAlgorithm $theAlgorithm)
    {
        //
    }

    /**
     * Engagement is the stars on the top-level commit plus every other developer taking part anywhere in its thread.
     * Replies, pending ones aside, count once per developer, so a long back-and-forth between two people is not a hit.
     *
     * Scores are counted once the star or reply is committed, with the commit locked, so two stars landing at the same
     * time cannot each count without the other: whichever counts last sees both.
     *
     * @param  array<int, string>  $commitIds
     */
    public function handle(array $commitIds): void
    {
        $commitIds = array_values(array_unique($commitIds));

        DB::afterCommit(fn () => DB::transaction(function () use ($commitIds): void {
            Commit::query()->whereKey($commitIds)->orderBy('id')->lockForUpdate()->get(['id']);

            Commit::query()
                ->whereKey($commitIds)
                ->topLevel()
                ->withCount('stars')
                ->addSelect(['repliers_count' => DB::table('commits as replies')
                    ->selectRaw('count(distinct replies.user_id)')
                    ->whereColumn('replies.root_id', 'commits.id')
                    ->whereColumn('replies.user_id', '!=', 'commits.user_id')
                    ->where('replies.status', CommitStatus::Approved)
                    ->whereNull('replies.deleted_at')])
                ->get()
                ->each(fn (Commit $commit): int => Commit::query()->whereKey($commit->id)->toBase()->update([
                    'hot_score' => $this->theAlgorithm->score($commit->created_at, $commit->stars_count, (int) $commit->repliers_count),
                ]));
        }));
    }
}
