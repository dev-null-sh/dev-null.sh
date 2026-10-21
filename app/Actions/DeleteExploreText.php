<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ExploreTopic;
use App\Queries\CommitBuilder;
use Illuminate\Support\Facades\DB;

final readonly class DeleteExploreText
{
    /** @param list<string>|CommitBuilder $commitIds */
    public function handle(array|CommitBuilder $commitIds): void
    {
        $topics = DB::table('explore_summary_sources')->whereIn('commit_id', $commitIds)->select('topic_id');
        ExploreTopic::query()->whereIn('id', $topics)->update([
            'summary' => null, 'summary_generated_at' => null, 'summary_dirty_at' => now(), 'summary_revision' => DB::raw('summary_revision + 1'),
        ]);
    }
}
