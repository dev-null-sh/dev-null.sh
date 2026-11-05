<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateFollowsFromPendingFollows
{
    public function __construct(private CreateFollowNotification $createFollowNotification)
    {
        //
    }

    /**
     * The members who waited for a developer to sign up follow them once they do, each with the usual follow
     * notification, which their inbox groups into one, and are told so, as following one by one would. A new user has
     * no followers yet, so none follows twice.
     */
    public function handle(User $user): void
    {
        $pending = DB::table('pending_follows')->where('github_id', $user->github_id);
        $followers = User::query()->whereIn('id', $pending->clone()->select('user_id'))->get();

        $user->followers()->attach($followers->modelKeys());

        foreach ($followers as $follower) {
            $this->createFollowNotification->handle($follower, $user);
        }

        $pending->delete();

        if ($followers->isNotEmpty()) {
            event(new UsersUpdated([...$followers->modelKeys(), $user->id], UserUpdate::Account));
            event(new UsersUpdated($followers->modelKeys(), UserUpdate::Relationships));
            event(new UsersUpdated($followers->modelKeys(), UserUpdate::Live));
            event(new ProfilesUpdated([...$followers->modelKeys(), $user->id], changesConnections: true));
        }
    }
}
