<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\UserList;

final readonly class DeleteUserListPin
{
    public function handle(UserList $list): void
    {
        $list->update(['pinned_at' => null]);
    }
}
