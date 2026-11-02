<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserAutomaticTranslation
{
    public function handle(User $user, bool $enabled): void
    {
        $user->update(['automatic_translation' => $enabled]);
    }
}
