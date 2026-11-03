<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitUpdate;
use App\Events\CommitsUpdated;
use App\Models\Commit;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;

final readonly class CreateCommitPollVote
{
    /**
     * The unique poll and voter pair keeps votes final: a repeated or concurrent second vote records nothing.
     */
    public function handle(User $user, PollOption $option): bool
    {
        $voted = PollVote::query()->insertOrIgnore([
            'poll_id' => $option->poll_id,
            'poll_option_id' => $option->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;

        // Results stay hidden from whoever hasn't voted, so the update only says to read them again.
        if ($voted) {
            CommitsUpdated::dispatch(Commit::query()->approved()->whereRelation('poll', 'id', $option->poll_id)->get(['id']), CommitUpdate::Poll);
        }

        return $voted;
    }
}
