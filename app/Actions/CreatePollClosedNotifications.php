<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class CreatePollClosedNotifications
{
    /**
     * Tells the author and everyone who voted that the results are final, as long as the commit is still approved. The
     * author is the actor of each one, so nobody hears about a poll by someone they mute or are across a block from.
     * Safe to call more than once.
     */
    public function handle(Poll $poll): void
    {
        $commit = $poll->commit;

        if (! $commit instanceof Commit || $commit->status !== CommitStatus::Approved) {
            return;
        }

        User::query()->whereKey($commit->user_id)->orWhereIn('id', $poll->votes()->select('user_id'))->get(['id'])
            ->map(fn (User $recipient): array => [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $recipient->id,
                'actor_id' => $commit->user_id,
                'type' => NotificationType::PollClosed->value,
                'commit_id' => $commit->id,
                'key' => 'poll_closed:'.$commit->id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->chunk(500)
            ->each(fn (Collection $notifications): int => Notification::query()->insertOrIgnore($notifications->values()->all()));
    }
}
