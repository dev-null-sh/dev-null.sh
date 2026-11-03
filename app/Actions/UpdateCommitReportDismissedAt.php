<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\CommitReport;
use App\Queries\AdminsQuery;

final readonly class UpdateCommitReportDismissedAt
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(CommitReport $report): void
    {
        CommitReport::query()->whereKey($report->id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);

        UsersUpdated::dispatch($this->admins->ids(), UserUpdate::Moderation, ['reports' => [$report->id]]);
    }
}
