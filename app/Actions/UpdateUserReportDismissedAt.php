<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\UserReport;
use App\Queries\AdminsQuery;

final readonly class UpdateUserReportDismissedAt
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(UserReport $report): void
    {
        UserReport::query()->whereKey($report->id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);

        event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation, ['user_reports' => [$report->id]]));
    }
}
