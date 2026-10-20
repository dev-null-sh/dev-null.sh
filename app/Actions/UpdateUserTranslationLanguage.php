<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserTranslationLanguage
{
    public function handle(User $user, ?string $language): void
    {
        $user->update(['translation_language' => $language]);
    }
}
