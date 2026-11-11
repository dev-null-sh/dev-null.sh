<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TopicType;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

final readonly class UpdateStaleHandles
{
    /**
     * GitHub handles are unique case-insensitively, so another profile holding this one renamed away on GitHub. The
     * placeholder starts with an underscore, which no GitHub handle can, and their next sync restores a real one.
     * Bots keep their identity under reserved handles so they can continue replying. An unclaimed profile, a developer
     * looked up from GitHub who never signed in, has nothing of its own, so it goes, with the mentions linked to it:
     * looking its new handle up brings it back, with the members waiting for it, kept by its GitHub ID.
     */
    public function handle(string $handle, string $githubId): void
    {
        Profile::query()
            ->where('handle', $handle)
            ->where('github_id', '!=', $githubId)
            ->each(function (Profile $profile): void {
                if ($profile->isUnclaimed()) {
                    DB::table('commit_topics')->where('topic_type', TopicType::Developer)->where('topic_id', $profile->id)->delete();
                    $profile->delete();

                    return;
                }

                $profile->update(['handle' => match ($profile->github_id) {
                    CreateWorldReply::GITHUB_ID => CreateWorldReply::FALLBACK_HANDLE,
                    CreateTomFriendship::GITHUB_ID => CreateTomFriendship::FALLBACK_HANDLE,
                    default => '_renamed-'.$profile->github_id,
                }]);
            });
    }
}
