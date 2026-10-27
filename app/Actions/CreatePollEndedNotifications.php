<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Poll;
use Illuminate\Support\Str;

final readonly class CreatePollEndedNotifications
{
    /**
     * A poll that closed tells every voter the results are in, once. The author is not notified: they set the poll
     * and see its results live. Safe to call more than once.
     */
    public function handle(Poll $poll): void
    {
        $voterIds = $poll->votes()->pluck('user_id')->unique()->values()->all();

        if ($voterIds === []) {
            return;
        }

        $notifications = [];

        foreach ($voterIds as $voterId) {
            $notifications[] = [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $voterId,
                'actor_id' => $poll->commit?->user_id,
                'type' => NotificationType::PollEnded->value,
                'commit_id' => $poll->commit_id,
                'subject_id' => $poll->commit_id,
                'key' => 'poll-ended:'.$poll->commit_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Notification::query()->insertOrIgnore($notifications);
    }
}
