<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CommitReport;

final readonly class UpdateCommitReportDismissedAt
{
    public function handle(CommitReport $report): void
    {
        CommitReport::query()->whereKey($report->id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);
    }
}
