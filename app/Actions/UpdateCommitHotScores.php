<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\ThreadEngagement;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Services\TheAlgorithm;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
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
     * Engagement is every other developer taking part in the thread, counted once for each thing they did: starring any
     * of its commits, quoting any of them others can see, replying anywhere in it, and having a reply answered by the
     * author. Each kind has its own weight in the score. A long back-and-forth between two people is still one replier,
     * and starring every reply is still one stargazer. Pending or deleted commits do not count, and neither does
     * anything the author does in their own thread.
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
     * Each chunk of threads is locked in the order of their ids, as the chunks are, so two rescores cannot deadlock,
     * and rescored with a single update.
     *
     * @param  array<int, string>  $rootIds
     */
    private function rescore(array $rootIds): void
    {
        Commit::query()->whereKey($rootIds)->orderBy('id')->lockForUpdate()->get(['id']);

        $commits = Commit::query()->whereKey($rootIds)->topLevel()->get(['id', 'user_id', 'created_at']);

        if ($commits->isEmpty()) {
            return;
        }

        $stargazersByThread = $this->usersByThread($this->stargazers($commits->modelKeys()));
        $quotersByThread = $this->usersByThread($this->quoters($commits->modelKeys()));
        $repliersByThread = $this->usersByThread($this->repliers($commits->modelKeys()));
        $repliersAnsweredByAuthorByThread = $this->usersByThread($this->repliersAnsweredByAuthor($commits->modelKeys()));

        $scores = $commits->flatMap(function (Commit $commit) use ($stargazersByThread, $quotersByThread, $repliersByThread, $repliersAnsweredByAuthorByThread): array {
            $author = $commit->user_id === null ? [] : [$commit->user_id => true];

            return [$commit->id, $this->theAlgorithm->score($commit->created_at, new ThreadEngagement(
                stargazers: count(array_diff_key($stargazersByThread[$commit->id] ?? [], $author)),
                quoters: count(array_diff_key($quotersByThread[$commit->id] ?? [], $author)),
                repliers: count(array_diff_key($repliersByThread[$commit->id] ?? [], $author)),
                repliersAnsweredByAuthor: count(array_diff_key($repliersAnsweredByAuthorByThread[$commit->id] ?? [], $author)),
            ))];
        });

        DB::update(
            'update commits set hot_score = cast(case id '.str_repeat('when ? then ? ', $commits->count()).'end as double precision)'
            .' where id in ('.implode(', ', array_fill(0, $commits->count(), '?')).')',
            [...$scores->all(), ...$commits->modelKeys()],
        );
    }

    /**
     * Who replied anywhere in each thread.
     *
     * @param  array<int, mixed>  $rootIds
     */
    private function repliers(array $rootIds): Builder
    {
        return $this->visibleCommits('replies')
            ->select(['replies.root_id as thread_id', 'replies.user_id'])
            ->whereIn('replies.root_id', $rootIds);
    }

    /**
     * Who replied in each thread and got a direct reply from its author, the heaviest engagement of all. Only an answer
     * counts, not a star, so an author cannot lift their thread by starring every reply.
     *
     * @param  array<int, mixed>  $rootIds
     */
    private function repliersAnsweredByAuthor(array $rootIds): Builder
    {
        return $this->visibleCommits('replies')
            ->select(['replies.root_id as thread_id', 'replies.user_id'])
            ->join('commits as roots', 'roots.id', '=', 'replies.root_id')
            ->join('commits as answers', fn (JoinClause $join): JoinClause => $join
                ->on('answers.parent_id', '=', 'replies.id')
                ->on('answers.user_id', '=', 'roots.user_id'))
            ->whereIn('replies.root_id', $rootIds)
            ->where('answers.status', CommitStatus::Approved)
            ->whereNull('answers.deleted_at');
    }

    /**
     * Who quoted the top-level commit of each thread, or one of its replies others can see, as one indexed query each
     * rather than one query matching either, which could not use the indexes.
     *
     * @param  array<int, mixed>  $rootIds
     */
    private function quoters(array $rootIds): Builder
    {
        return $this->visibleCommits('quotes')
            ->select(['quotes.quoted_commit_id as thread_id', 'quotes.user_id'])
            ->whereIn('quotes.quoted_commit_id', $rootIds)
            ->union($this->visibleCommits('quotes')
                ->select(['quoted.root_id as thread_id', 'quotes.user_id'])
                ->join('commits as quoted', 'quoted.id', '=', 'quotes.quoted_commit_id')
                ->whereIn('quoted.root_id', $rootIds)
                ->where('quoted.status', CommitStatus::Approved));
    }

    /**
     * Who starred the top-level commit of each thread, or one of its replies others can see, as one indexed query each.
     *
     * @param  array<int, mixed>  $rootIds
     */
    private function stargazers(array $rootIds): Builder
    {
        return DB::table('stars')
            ->select(['stars.commit_id as thread_id', 'stars.user_id'])
            ->whereIn('stars.commit_id', $rootIds)
            ->union(DB::table('stars')
                ->select(['starred.root_id as thread_id', 'stars.user_id'])
                ->join('commits as starred', 'starred.id', '=', 'stars.commit_id')
                ->whereIn('starred.root_id', $rootIds)
                ->where('starred.status', CommitStatus::Approved)
                ->whereNull('starred.deleted_at'));
    }

    /**
     * Each developer once per thread, from commits others can see.
     */
    private function visibleCommits(string $alias): Builder
    {
        return DB::table("commits as {$alias}")
            ->distinct()
            ->where("{$alias}.status", CommitStatus::Approved)
            ->whereNull("{$alias}.deleted_at");
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function usersByThread(Builder $query): array
    {
        $usersByThread = [];

        foreach ($query->get() as $row) {
            if (is_string($row->thread_id) && is_string($row->user_id)) {
                $usersByThread[$row->thread_id][$row->user_id] = true;
            }
        }

        return $usersByThread;
    }
}
