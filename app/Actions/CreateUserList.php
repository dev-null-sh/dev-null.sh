<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\UserList;

final readonly class CreateUserList
{
    public function handle(User $user, string $name): UserList
    {
        return $user->lists()->create(['name' => $name]);
    }
}
