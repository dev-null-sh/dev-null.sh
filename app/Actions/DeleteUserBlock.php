<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class DeleteUserBlock
{
    public function handle(User $user, User $blocked): void
    {
        $user->blocking()->detach($blocked->id);
    }
}
