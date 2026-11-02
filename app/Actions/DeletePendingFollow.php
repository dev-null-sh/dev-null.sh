<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeletePendingFollow
{
    /**
     * The user no longer follows the developer when they sign up, whether they waited from their page or from GitHub.
     */
    public function handle(User $user, Profile $profile): void
    {
        DB::table('pending_follows')->where('user_id', $user->id)->where('profile_id', $profile->id)->delete();
    }
}
