<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Models\User;

final readonly class CreateBranchWatch
{
    public function handle(User $user, Branch $branch): void
    {
        if ($branch->watches()->where('user_id', $user->id)->exists()) {
            return;
        }

        $branch->watches()->create(['user_id' => $user->id]);
    }
}
