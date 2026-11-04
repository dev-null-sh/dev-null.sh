<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Headline;

final readonly class DeleteExpiredHeadlineSummaries
{
    public function handle(): void
    {
        Headline::query()->where('summary_created_at', '<=', now()->subHours(Headline::SUMMARY_HOURS))
            ->update(['summary' => null, 'summary_created_at' => null]);
    }
}
