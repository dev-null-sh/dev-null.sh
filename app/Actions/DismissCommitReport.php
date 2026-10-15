<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CommitReport;
use Illuminate\Support\Facades\Gate;

final readonly class DismissCommitReport
{
    public function handle(CommitReport $report): void
    {
        Gate::authorize('dismiss', $report);

        CommitReport::query()->whereKey($report->id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);
    }
}
