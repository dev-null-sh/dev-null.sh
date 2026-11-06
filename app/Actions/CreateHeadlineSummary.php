<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\HeadlineProvider;
use App\Models\Headline;
use App\Queries\HeadlineSummaryCommitsQuery;
use App\Queries\HotHeadlinesQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class CreateHeadlineSummary
{
    public function __construct(private HeadlineProvider $provider, private HeadlineSummaryCommitsQuery $sources, private HotHeadlinesQuery $hotHeadlines)
    {
        //
    }

    /**
     * AI runs outside the database transaction. Publication and source invalidation serialize on the headline row;
     * rechecking the selected bodies under that lock prevents publishing a recap of content already removed.
     */
    public function handle(string $headlineId, bool $refresh = false): void
    {
        Cache::lock('headline-summary:'.$headlineId, 180)->get(function () use ($headlineId, $refresh): void {
            $headline = Headline::query()->find($headlineId);

            if (! $headline instanceof Headline || (! $refresh && $headline->summary_created_at?->gt(now()->subHours(Headline::SUMMARY_HOURS)))) {
                return;
            }

            if (! in_array($headline->name, array_column($this->hotHeadlines->get(), 'name'), true)) {
                return;
            }

            $until = now();
            $bodies = $this->sources->get($headline, $until);

            if ($bodies === []) {
                return;
            }

            $summary = $this->provider->summarize($headline->name, array_values($bodies));

            if ($summary === null) {
                return;
            }

            DB::transaction(function () use ($headlineId, $headline, $bodies, $summary, $until): void {
                $current = Headline::query()->lockForUpdate()->find($headlineId);

                if (! $current instanceof Headline
                    || $current->getRawOriginal('summary_created_at') !== $headline->getRawOriginal('summary_created_at')
                    || $this->sources->get($current, $until) !== $bodies) {
                    return;
                }

                $current->update(['summary' => $summary, 'summary_created_at' => now()]);
            }, attempts: 3);
        });
    }
}
