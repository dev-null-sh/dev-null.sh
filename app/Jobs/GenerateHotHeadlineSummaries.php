<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DeleteExpiredHeadlineSummaries;
use App\Models\Headline;
use App\Queries\HotHeadlinesQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateHotHeadlineSummaries implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(HotHeadlinesQuery $hotHeadlines, DeleteExpiredHeadlineSummaries $deleteExpired): void
    {
        $deleteExpired->handle();

        Headline::query()->whereIn('name', array_column($hotHeadlines->get(), 'name'))
            ->where(fn (Builder $query): Builder => $query->whereNull('summary_created_at')
                ->orWhere('summary_created_at', '<=', now()->subHours(Headline::SUMMARY_HOURS)))
            ->get(['id'])
            ->each(fn (Headline $headline) => dispatch(new GenerateHeadlineSummary($headline->id))->afterCommit());
    }
}
