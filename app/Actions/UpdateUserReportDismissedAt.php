<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\UserReport;

final readonly class UpdateUserReportDismissedAt
{
    public function handle(UserReport $report): void
    {
        UserReport::query()->whereKey($report->id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);
    }
}
