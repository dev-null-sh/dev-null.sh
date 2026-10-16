<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Scores now double every six hours instead of twelve, so every stored score is on another scale than the new ones
     * and must be refilled, or any commit rescored after the deploy would rank above every commit that was not. Stars on
     * the replies of a thread count too now, each developer once, and not again when they reply or quote in it.
     *
     * The formula of App\Services\TheAlgorithm at the time is copied, so this migration keeps filling the same scores
     * whatever the app becomes. They are computed from scratch, so running it again after a failure gives the same result.
     */
    public function up(): void
    {
        DB::table('commits')
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->select(['id', 'user_id', 'created_at'])
            ->chunkById(500, function (Collection $commits): void {
                $rootIds = $commits->pluck('id')->all();
                $repliersByThread = $this->usersByThread($this->repliers($rootIds));
                $stargazersByThread = $this->usersByThread($this->stargazers($rootIds));

                foreach ($commits as $commit) {
                    if (! is_string($commit->id) || ! is_string($commit->created_at)) {
                        continue;
                    }

                    $author = is_string($commit->user_id) ? [$commit->user_id => true] : [];
                    $repliers = array_diff_key($repliersByThread[$commit->id] ?? [], $author);
                    $stargazers = array_diff_key($stargazersByThread[$commit->id] ?? [], $author, $repliers);

                    $engagement = 1 + count($stargazers) + 3 * count($repliers);

                    DB::table('commits')->where('id', $commit->id)->update([
                        'hot_score' => round(log($engagement, 2) + Date::parse($commit->created_at)->getTimestamp() / 21600, 6),
                    ]);
                }
            });
    }

    /**
     * @param  array<mixed>  $rootIds
     */
    private function repliers(array $rootIds): Builder
    {
        $engagements = fn (): Builder => DB::table('commits as engagements')
            ->distinct()
            ->where('engagements.status', 'approved')
            ->whereNull('engagements.deleted_at');

        return $engagements()
            ->select(['engagements.root_id as thread_id', 'engagements.user_id'])
            ->whereIn('engagements.root_id', $rootIds)
            ->union($engagements()
                ->select(['engagements.quoted_commit_id as thread_id', 'engagements.user_id'])
                ->whereIn('engagements.quoted_commit_id', $rootIds))
            ->union($engagements()
                ->select(['quoted.root_id as thread_id', 'engagements.user_id'])
                ->join('commits as quoted', 'quoted.id', '=', 'engagements.quoted_commit_id')
                ->whereIn('quoted.root_id', $rootIds)
                ->where('quoted.status', 'approved'));
    }

    /**
     * @param  array<mixed>  $rootIds
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
                ->where('starred.status', 'approved')
                ->whereNull('starred.deleted_at'));
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
};
