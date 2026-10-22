<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserCoverImageApproval
{
    public function handle(User $user): void
    {
        User::withoutTimestamps(fn (): int => User::query()->whereKey($user->id)->update(['cover_image_approved_at' => now()]));
    }
}
