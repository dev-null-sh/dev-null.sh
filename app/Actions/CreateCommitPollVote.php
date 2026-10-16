<?php

declare(strict_types=1);

namespace App\Actions;

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
        return PollVote::query()->insertOrIgnore([
            'poll_id' => $option->poll_id,
            'poll_option_id' => $option->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
