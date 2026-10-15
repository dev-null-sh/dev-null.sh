<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserTrust
{
    public function handle(User $user, User $moderator, bool $trusted): void
    {
        DB::transaction(function () use ($user, $moderator, $trusted): void {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($user->is_trusted === $trusted) {
                return;
            }

            $user->update(['is_trusted' => $trusted]);
            $user->trustChanges()->create([
                'actor_id' => $moderator->id,
                'is_trusted' => $trusted,
            ]);
        });
    }
}
