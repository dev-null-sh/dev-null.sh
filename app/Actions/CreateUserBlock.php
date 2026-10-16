<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserBlock
{
    /**
     * Blocking works both ways, so it also ends the follows in both directions.
     */
    public function handle(User $user, User $blocked): void
    {
        DB::transaction(function () use ($user, $blocked): void {
            $user->blocking()->syncWithoutDetaching([$blocked->id]);

            $user->following()->detach($blocked->id);
            $blocked->following()->detach($user->id);
        });
    }
}
