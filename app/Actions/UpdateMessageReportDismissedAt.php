<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\MessageReport;
use App\Queries\AdminsQuery;

final readonly class UpdateMessageReportDismissedAt
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(MessageReport $report): void
    {
        MessageReport::query()->whereKey($report->id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);

        event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation, ['message_reports' => [$report->id]]));
    }
}
