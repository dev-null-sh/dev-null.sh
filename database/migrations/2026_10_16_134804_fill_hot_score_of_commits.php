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
     * The formula of App\Services\TheAlgorithm when the scores were first filled, copied so this migration keeps
     * filling the same scores whatever the app becomes. They are computed from scratch, so running it again after a
     * failure gives the same result.
     */
    public function up(): void
    {
        DB::table('commits')
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->select(['id', 'created_at'])
            ->selectSub(fn (Builder $query): Builder => $query
                ->from('stars')
                ->selectRaw('count(*)')
                ->whereColumn('stars.commit_id', 'commits.id'), 'stars_count')
            ->selectSub(fn (Builder $query): Builder => $query
                ->from('commits as replies')
                ->selectRaw('count(distinct replies.user_id)')
                ->whereColumn('replies.root_id', 'commits.id')
                ->whereColumn('replies.user_id', '!=', 'commits.user_id')
                ->where('replies.status', 'approved')
                ->whereNull('replies.deleted_at'), 'repliers_count')
            ->chunkById(500, function (Collection $commits): void {
                foreach ($commits as $commit) {
                    if (! is_numeric($commit->stars_count) || ! is_numeric($commit->repliers_count) || ! is_string($commit->created_at)) {
                        continue;
                    }

                    $engagement = 1 + $commit->stars_count + 3 * $commit->repliers_count;

                    DB::table('commits')->where('id', $commit->id)->update([
                        'hot_score' => round(log($engagement, 2) + Date::parse($commit->created_at)->getTimestamp() / 43200, 6),
                    ]);
                }
            });
    }
};
