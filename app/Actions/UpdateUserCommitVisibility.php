<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserCommitVisibility
{
    /**
     * @param  list<string>  $hiddenCommitChannels
     */
    public function handle(User $user, array $hiddenCommitChannels): void
    {
        $user->update(['hidden_commit_channels' => $hiddenCommitChannels]);
    }
}
