<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\GitHubProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors a GitHub profile onto the user, whether it came from signing in or from the daily refresh.
 */
final readonly class UpdateUserProfile
{
    public function handle(User $user, GitHubProfile $profile): User
    {
        return DB::transaction(function () use ($user, $profile): User {
            $this->freeUsername($profile->username, $user->github_id);

            $user->fill([
                ...$profile->attributes(),
                'github_synced_at' => now(),
            ])->save();

            return $user;
        });
    }

    /**
     * GitHub usernames are unique case-insensitively, so another user holding this one renamed away on GitHub. The
     * placeholder starts with an underscore, which no GitHub username can, and their next sync restores a real one.
     */
    private function freeUsername(string $username, string $githubId): void
    {
        User::query()
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
            ->where('github_id', '!=', $githubId)
            ->each(function (User $user): void {
                $user->update(['username' => '_renamed-'.$user->github_id]);
            });
    }
}
