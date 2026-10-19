<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserFederation
{
    public function __construct(private CreateUserKeyPair $createUserKeyPair)
    {
        //
    }

    public function handle(User $user, bool $enabled): void
    {
        if ($enabled) {
            $this->createUserKeyPair->handle($user);
        }

        $user->update(['federates' => $enabled]);
    }
}
