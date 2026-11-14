<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Jobs\SendNotificationPush;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateFollowNotification
{
    /**
     * A follow notifies once per follower, ever: unfollowing and following again brings
     * back the same notification instead of pinging the followed user again, on the web or their phone. Nobody hears from people they mute.
     */
    public function handle(User $follower, User $followed): void
    {
        if ($followed->isMuting($follower)) {
            return;
        }

        $id = Str::uuid7()->toString();

        Notification::query()->insertOrIgnore([
            'id' => $id,
            'recipient_id' => $followed->id,
            'actor_id' => $follower->id,
            'type' => NotificationType::Followed->value,
            'commit_id' => null,
            'key' => 'followed:'.$follower->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        event(new UsersUpdated([$followed->id], UserUpdate::Notifications));

        SendNotificationPush::dispatchFor([$id]);
    }
}
