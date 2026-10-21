<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ExploreTopic;
use Illuminate\Support\Facades\DB;

final readonly class UpdateExploreReport
{
    public function handle(ExploreTopic $topic, ?bool $suppressed): void
    {
        DB::transaction(function () use ($topic, $suppressed): void {
            if ($suppressed !== null) {
                $topic->update(['suppressed_at' => $suppressed ? now() : null]);
            }

            DB::table('explore_reports')->where('topic_id', $topic->id)->whereNull('resolved_at')->update(['resolved_at' => now()]);
        });
    }
}
