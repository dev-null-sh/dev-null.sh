<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateStaleUsernames
{
    /**
     * GitHub usernames are unique case-insensitively, so another user holding this one renamed away on GitHub. The
     * placeholder starts with an underscore, which no GitHub username can, and their next sign-in restores a real one.
     * Bots keep their identity under reserved handles so they can continue replying.
     */
    public function handle(string $username, string $githubId): void
    {
        User::query()
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
            ->where('github_id', '!=', $githubId)
            ->each(function (User $user): void {
                $user->update(['username' => match ($user->github_id) {
                    CreateWorldReply::GITHUB_ID => CreateWorldReply::FALLBACK_USERNAME,
                    CreateTomFriendship::GITHUB_ID => CreateTomFriendship::FALLBACK_USERNAME,
                    default => '_renamed-'.$user->github_id,
                }]);
            });
    }
}
