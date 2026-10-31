<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateStarNotification
{
    /**
     * A star notifies the commit's author once while it holds: unstarring removes the notification.
     */
    public function handle(User $stargazer, Commit $commit): void
    {
        // Nobody is told about stars on an organization's commit whose poster has since deleted their account.
        if ($commit->user_id === null || $commit->user_id === $stargazer->id) {
            return;
        }

        Notification::query()->insertOrIgnore([
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $commit->user_id,
            'actor_id' => $stargazer->id,
            'type' => NotificationType::Starred->value,
            'commit_id' => $commit->id,
            'subject_id' => $commit->id,
            'key' => 'starred:'.$commit->id.':'.$stargazer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
