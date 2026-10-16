<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Services\TheAlgorithm;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommitHotScores
{
    private const int CHUNK_SIZE = 1000;

    public function __construct(private TheAlgorithm $theAlgorithm)
    {
        //
    }

    /**
     * Rescores the threads the given commits are part of, and for quotes, the thread of the quoted commit too. The
     * threads are found right away, so callers deleting commits can pass them before they are gone.
     *
     * Engagement is the stars on the top-level commit plus every other developer taking part in its thread: replying
     * anywhere in it, or quoting any of its commits others can see, since quotes show among the replies. Each developer
     * counts once, so a long back-and-forth between two people is not a hit, and pending or deleted commits do not count.
     *
     * Scores are counted once the change is committed, with the commit locked, so two stars landing at the same time
     * cannot each count without the other: whichever counts last sees both.
     *
     * @param  array<int, string>  $commitIds
     */
    public function handle(array $commitIds): void
    {
        $rootIds = collect($commitIds)
            ->chunk(self::CHUNK_SIZE)
            ->flatMap(fn (Collection $ids): EloquentCollection => Commit::query()
                ->withTrashed()
                ->whereKey($ids->all())
                ->with(['quotedCommit' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->select(['id', 'root_id'])])
                ->get(['id', 'root_id', 'quoted_commit_id']))
            ->flatMap(fn (Commit $commit): array => [$commit->threadRootId(), $commit->quotedCommit?->threadRootId()])
            ->filter()
            ->unique()
            ->sort(SORT_STRING)
            ->values()
            ->all();

        if ($rootIds === []) {
            return;
        }

        DB::afterCommit(fn () => DB::transaction(function () use ($rootIds): void {
            foreach (array_chunk($rootIds, self::CHUNK_SIZE) as $chunk) {
                $this->rescore($chunk);
            }
        }));
    }

    /**
     * Each chunk of threads is locked in the order of their ids, as the chunks are, and rescored with a single update.
     *
     * @param  array<int, string>  $rootIds
     */
    private function rescore(array $rootIds): void
    {
        Commit::query()->whereKey($rootIds)->orderBy('id')->lockForUpdate()->get(['id']);

        $commits = Commit::query()->whereKey($rootIds)->topLevel()->select(['id', 'user_id', 'created_at'])->withCount('stars')->get();

        if ($commits->isEmpty()) {
            return;
        }

        $repliersByThread = $this->repliersByThread($commits->modelKeys());

        $scores = $commits->flatMap(function (Commit $commit) use ($repliersByThread): array {
            $repliers = array_diff_key($repliersByThread[$commit->id] ?? [], $commit->user_id === null ? [] : [$commit->user_id => true]);

            return [$commit->id, $this->theAlgorithm->score($commit->created_at, $commit->stars_count, count($repliers))];
        });

        DB::update(
            'update commits set hot_score = cast(case id '.str_repeat('when ? then ? ', $commits->count()).'end as double precision)'
            .' where id in ('.implode(', ', array_fill(0, $commits->count(), '?')).')',
            [...$scores->all(), ...$commits->modelKeys()],
        );
    }

    /**
     * Who replied in each thread, quoted its top-level commit, or quoted one of its replies, as one indexed query each
     * rather than one query matching any of them, which could not use the indexes.
     *
     * @param  array<int, mixed>  $rootIds
     * @return array<string, array<string, true>>
     */
    private function repliersByThread(array $rootIds): array
    {
        $engagements = fn (): Builder => DB::table('commits as engagements')
            ->distinct()
            ->where('engagements.status', CommitStatus::Approved)
            ->whereNull('engagements.deleted_at');

        $rows = $engagements()
            ->select(['engagements.root_id as thread_id', 'engagements.user_id'])
            ->whereIn('engagements.root_id', $rootIds)
            ->union($engagements()
                ->select(['engagements.quoted_commit_id as thread_id', 'engagements.user_id'])
                ->whereIn('engagements.quoted_commit_id', $rootIds))
            ->union($engagements()
                ->select(['quoted.root_id as thread_id', 'engagements.user_id'])
                ->join('commits as quoted', 'quoted.id', '=', 'engagements.quoted_commit_id')
                ->whereIn('quoted.root_id', $rootIds)
                ->where('quoted.status', CommitStatus::Approved))
            ->get();

        $repliersByThread = [];

        foreach ($rows as $row) {
            if (is_string($row->thread_id) && is_string($row->user_id)) {
                $repliersByThread[$row->thread_id][$row->user_id] = true;
            }
        }

        return $repliersByThread;
    }
}
