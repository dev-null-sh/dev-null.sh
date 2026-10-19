<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Models\User;

final readonly class DeleteBranchWatch
{
    public function handle(User $user, Branch $branch): void
    {
        $branch->watches()->where('user_id', $user->id)->delete();
    }
}
