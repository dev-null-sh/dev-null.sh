<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class CreateSuggestionDismissal
{
    /**
     * Dismissing is for good and only keeps someone out of who to follow: their commits and following them stay as they are.
     */
    public function handle(User $user, User $dismissed): void
    {
        $user->dismissedSuggestions()->syncWithoutDetaching([$dismissed->id]);
    }
}
