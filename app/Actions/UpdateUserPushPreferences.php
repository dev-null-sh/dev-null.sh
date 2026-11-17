<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PushPreference;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;

final readonly class UpdateUserPushPreferences
{
    /**
     * @param  array<value-of<PushPreference>, bool>  $preferences
     */
    public function handle(User $user, array $preferences): void
    {
        $user->update(collect($preferences)->mapWithKeys(fn (bool $on, string $preference): array => [
            PushPreference::from($preference)->column() => $on,
        ])->all());

        event(new UsersUpdated([$user->id], UserUpdate::Account));
    }
}
