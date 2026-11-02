<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreatePendingFollow
{
    /**
     * The user follows the developer once they sign up. Waiting twice is waiting once. The profile is locked, as signing
     * up turns the waits into follows under its lock: a wait for someone who just signed up, or whose profile just went,
     * is dropped rather than left behind.
     */
    public function handle(User $user, Profile $profile): void
    {
        DB::transaction(function () use ($user, $profile): void {
            if (Profile::query()->lockForUpdate()->find($profile->id)?->isUnclaimed() !== true) {
                return;
            }

            DB::table('pending_follows')->insertOrIgnore(['user_id' => $user->id, 'profile_id' => $profile->id]);
        });
    }
}
