<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Foundation\Queue\Queueable;

final class CheckStreakShields implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Include inactive users: a missed day notifies even when nobody opens their profile.
     */
    public function handle(): void
    {
        User::query()->whereNull('banned_at')->whereHas('commits')
            ->eachById(fn (User $user): PendingDispatch => dispatch(new CheckUserStreakShields($user)));
    }
}
