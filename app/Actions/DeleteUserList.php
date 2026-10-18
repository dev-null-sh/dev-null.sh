<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\UserList;

final readonly class DeleteUserList
{
    /**
     * Its members are only taken off the list, so nobody hears about it.
     */
    public function handle(UserList $list): void
    {
        $list->delete();
    }
}
