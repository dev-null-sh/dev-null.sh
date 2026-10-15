<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class VoteOnPoll
{
    /**
     * Votes are final: the unique poll and user pair makes a second vote, even a concurrent one, a no-op that is reported back.
     */
    public function handle(User $user, PollOption $option): void
    {
        $poll = $option->poll;
        $commit = $poll->commit;

        if (! $commit instanceof Commit || $commit->status !== CommitStatus::Approved || $poll->opened_at === null) {
            throw ValidationException::withMessages(['poll' => __('This poll is not open for votes.')]);
        }

        if ($commit->user_id === $user->id) {
            throw ValidationException::withMessages(['poll' => __("You can't vote on your own poll.")]);
        }

        if ($poll->isClosed()) {
            throw ValidationException::withMessages(['poll' => __('This poll has closed.')]);
        }

        $recorded = PollVote::query()->insertOrIgnore([
            'poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($recorded === 0) {
            throw ValidationException::withMessages(['poll' => __('You have already voted on this poll.')]);
        }
    }
}
