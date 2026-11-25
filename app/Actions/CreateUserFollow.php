<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserFollow
{
    public function __construct(private CreateFollowNotification $createFollowNotification)
    {
        //
    }

    /**
     * Inserted unless already there rather than synced, which would read every follow the follower already has.
     *
     * The request checks for a block before this runs, but a block made at the same moment ends the follows before this
     * one is saved. So both users are locked, in the order a block locks them, and the block is checked again here: a
     * follow across a block changes nothing.
     */
    public function handle(User $follower, User $followed): void
    {
        DB::transaction(function () use ($follower, $followed): void {
            User::query()->whereKey([$follower->id, $followed->id])->orderBy('id')->lockForUpdate()->get();

            if ($follower->hasBlockWith($followed)) {
                return;
            }

            $follower->following()->newPivotStatement()->insertOrIgnore([
                'follower_id' => $follower->id,
                'followed_id' => $followed->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->createFollowNotification->handle($follower, $followed);

            event(new UsersUpdated([$follower->id, $followed->id], UserUpdate::Account));
            event(new UsersUpdated([$follower->id], UserUpdate::Relationships));
            event(new UsersUpdated([$follower->id], UserUpdate::Live));
            event(new ProfilesUpdated([$follower->id, $followed->id], changesConnections: true));
        });
    }
}
