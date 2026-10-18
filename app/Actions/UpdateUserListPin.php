<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\UserList;

final readonly class UpdateUserListPin
{
    /**
     * Pinning again keeps the list where it already sits among the tabs.
     */
    public function handle(UserList $list): void
    {
        if (! $list->isPinned()) {
            $list->update(['pinned_at' => now()]);
        }
    }
}
