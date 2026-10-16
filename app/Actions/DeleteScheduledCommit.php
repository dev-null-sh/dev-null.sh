<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ScheduledCommit;
use App\Models\User;

final readonly class DeleteScheduledCommit
{
    public function handle(User $user, ScheduledCommit $scheduled): bool
    {
        return ScheduledCommit::query()->whereKey($scheduled->id)->where('user_id', $user->id)
            ->whereNull('submitted_at')->whereNull('canceled_at')
            ->update(['canceled_at' => now(), 'body' => null]) === 1;
    }
}
