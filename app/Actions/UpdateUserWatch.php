<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserWatch
{
    /**
     * Only a follow can be watched, so this does nothing for someone the user doesn't follow.
     */
    public function handle(User $user, User $watched, bool $watching): void
    {
        $user->following()->newPivotQuery()
            ->where('followed_id', $watched->id)
            ->update(['watching' => $watching, 'updated_at' => now()]);
    }
}
