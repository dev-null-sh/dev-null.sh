<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class NotifyAboutFollow
{
    /**
     * A follow notifies once per follower, ever: unfollowing and following again brings
     * back the same notification instead of pinging the followed user again.
     */
    public function handle(User $follower, User $followed): void
    {
        Notification::query()->insertOrIgnore([
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $followed->id,
            'actor_id' => $follower->id,
            'type' => NotificationType::Followed->value,
            'commit_id' => null,
            'key' => 'followed:'.$follower->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
