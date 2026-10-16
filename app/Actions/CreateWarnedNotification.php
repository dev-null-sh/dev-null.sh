<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use Illuminate\Support\Str;

final readonly class CreateWarnedNotification
{
    /**
     * Warns the author once per commit, ever, that it now carries a community note. Like moderation updates, the
     * author is their own actor.
     */
    public function handle(Commit $commit): void
    {
        if ($commit->user_id === null) {
            return;
        }

        Notification::query()->insertOrIgnore([
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $commit->user_id,
            'actor_id' => $commit->user_id,
            'type' => NotificationType::Warned->value,
            'commit_id' => $commit->id,
            'key' => 'warned:'.$commit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
