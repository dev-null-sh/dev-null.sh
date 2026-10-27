<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Profile;

final readonly class UpdateStaleHandles
{
    /**
     * GitHub handles are unique case-insensitively, so another profile holding this one renamed away on GitHub. The
     * placeholder starts with an underscore, which no GitHub handle can, and their next sync restores a real one.
     * Bots keep their identity under reserved handles so they can continue replying.
     */
    public function handle(string $handle, string $githubId): void
    {
        Profile::query()
            ->whereRaw('LOWER(handle) = ?', [mb_strtolower($handle)])
            ->where('github_id', '!=', $githubId)
            ->each(function (Profile $profile): void {
                $profile->update(['handle' => match ($profile->github_id) {
                    CreateWorldReply::GITHUB_ID => CreateWorldReply::FALLBACK_HANDLE,
                    CreateTomFriendship::GITHUB_ID => CreateTomFriendship::FALLBACK_HANDLE,
                    default => '_renamed-'.$profile->github_id,
                }]);
            });
    }
}
