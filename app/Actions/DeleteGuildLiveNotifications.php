<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\Notification;

final readonly class DeleteGuildLiveNotifications
{
    /**
     * Drops the "this guild is live" alerts. The stream ending, or a new one starting, both call this.
     */
    public function handle(Guild $guild): void
    {
        $recipientIds = Notification::query()
            ->where('guild_id', $guild->id)
            ->where('type', NotificationType::GuildLive)
            ->pluck('recipient_id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        Notification::query()
            ->where('guild_id', $guild->id)
            ->where('type', NotificationType::GuildLive)
            ->delete();

        event(new UsersUpdated($recipientIds, UserUpdate::Notifications));
    }
}
