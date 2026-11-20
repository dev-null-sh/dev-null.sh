<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserStreakShields
{
    public function handle(User $user, bool $enabled): void
    {
        DB::transaction(function () use ($user, $enabled): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('streak_shield_preferences')->upsert([
                'user_id' => $user->id,
                'effective_on' => today()->toDateString(),
                'enabled' => $enabled,
            ], ['user_id', 'effective_on'], ['enabled']);
            $lockedUser->update(['automatic_streak_shields' => $enabled]);
            $user->refresh();
        });

        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
