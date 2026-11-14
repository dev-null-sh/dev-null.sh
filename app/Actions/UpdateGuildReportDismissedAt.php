<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\GuildReport;
use App\Queries\AdminsQuery;

final readonly class UpdateGuildReportDismissedAt
{
    public function __construct(private AdminsQuery $admins)
    {
        //
    }

    public function handle(GuildReport $report): void
    {
        $report->update(['dismissed_at' => now()]);
        event(new UsersUpdated($this->admins->ids(), UserUpdate::Moderation));
    }
}
