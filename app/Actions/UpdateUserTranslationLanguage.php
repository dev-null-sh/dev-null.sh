<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserTranslationLanguage
{
    public function handle(User $user, ?string $language): void
    {
        $user->update(['translation_language' => $language]);

        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
