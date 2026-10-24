<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\UserList;

final readonly class DeleteUserListMember
{
    public function handle(UserList $list, User $member): void
    {
        $list->members()->detach($member->id);
    }
}
