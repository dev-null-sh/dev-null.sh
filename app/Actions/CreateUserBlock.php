<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserBlock
{
    /**
     * Blocking works both ways, so it also ends the follows in both directions. Both users are locked, as a follow
     * between them does, so a follow sent at the same moment lands either before the block, which ends it, or after,
     * which sees it.
     */
    public function handle(User $user, User $blocked): void
    {
        DB::transaction(function () use ($user, $blocked): void {
            User::query()->whereKey([$user->id, $blocked->id])->orderBy('id')->lockForUpdate()->get();

            $user->blocking()->syncWithoutDetaching([$blocked->id]);

            $user->following()->detach($blocked->id);
            $blocked->following()->detach($user->id);

            // Nothing crosses a block: notifications, follows and remotes all change for both of them.
            event(new ProfilesUpdated([$user->id, $blocked->id], changesConnections: true));

            foreach ([UserUpdate::Notifications, UserUpdate::Account, UserUpdate::Relationships, UserUpdate::Live, UserUpdate::Inbox] as $update) {
                event(new UsersUpdated([$user->id, $blocked->id], $update));
            }
        });
    }
}
