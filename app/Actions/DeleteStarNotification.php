<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;

final readonly class DeleteStarNotification
{
    public function handle(User $stargazer, Commit $commit): void
    {
        Notification::query()
            ->where('type', NotificationType::Starred)
            ->where('actor_id', $stargazer->id)
            ->where('commit_id', $commit->id)
            ->delete();
    }
}
