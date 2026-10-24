<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserKnownLanguages
{
    /**
     * @param  list<string>  $languages
     */
    public function handle(User $user, array $languages): void
    {
        $user->update(['known_languages' => $languages === [] ? null : $languages]);
    }
}
