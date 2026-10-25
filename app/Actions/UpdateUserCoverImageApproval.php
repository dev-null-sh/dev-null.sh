<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserCoverImageApproval
{
    /**
     * Only the cover the moderator saw is approved: one the owner uploads in the meantime keeps waiting for review.
     */
    public function handle(User $user): void
    {
        User::withoutTimestamps(fn (): int => User::query()
            ->whereKey($user->id)
            ->where('cover_image_path', $user->cover_image_path)
            ->whereNull('cover_image_approved_at')
            ->update(['cover_image_approved_at' => now()]));
    }
}
