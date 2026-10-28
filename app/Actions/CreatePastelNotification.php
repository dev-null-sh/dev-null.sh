<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreatePastelNotification
{
    /**
     * The first pastel a commit wins tells its author while repeats stay quiet: the commit and the author keep
     * counting every pastel. Safe to call more than once.
     */
    public function handle(User $sender, Commit $commit): void
    {
        if ($commit->user_id === $sender->id) {
            return;
        }

        Notification::query()->insertOrIgnore([
            'id' => Str::uuid7()->toString(),
            'recipient_id' => $commit->user_id,
            'actor_id' => $sender->id,
            'type' => NotificationType::Awarded->value,
            'commit_id' => $commit->id,
            'subject_id' => $commit->id,
            'key' => 'awarded:'.$commit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
