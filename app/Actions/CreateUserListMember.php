<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\UserList;

final readonly class CreateUserListMember
{
    /**
     * Lists are private, so the member is not notified.
     */
    public function handle(UserList $list, User $member): void
    {
        $list->members()->syncWithoutDetaching([$member->id]);
    }
}
