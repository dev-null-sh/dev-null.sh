<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\ProcessExplore;
use App\Models\Commit;
use App\Queries\ExploreContentQuery;
use Illuminate\Support\Facades\DB;

final readonly class CreateExploreWork
{
    public function __construct(private ExploreContentQuery $content)
    {
        //
    }

    /** @param list<string> $commitIds */
    public function handle(array $commitIds, bool $backfill = false): void
    {
        DB::afterCommit(function () use ($commitIds, $backfill): void {
            $rows = $this->content->eligible()->whereKey($commitIds)->get(['id', 'created_at'])
                ->map(fn (Commit $commit): array => ['commit_id' => $commit->id, 'eligible_at' => $backfill ? $commit->created_at : now()])->all();

            if ($rows !== []) {
                DB::table('explore_classification_work')->insertOrIgnore($rows);

                if ($this->content->waiting()->count() >= config()->integer('explore.cohort_size')) {
                    dispatch(new ProcessExplore())->afterCommit();
                }
            }
        });
    }
}
