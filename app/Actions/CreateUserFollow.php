<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserFollow
{
    public function __construct(private CreateFollowNotification $createFollowNotification)
    {
        //
    }

    public function handle(User $follower, User $followed): void
    {
        DB::transaction(function () use ($follower, $followed): void {
            $follower->following()->syncWithoutDetaching([$followed->id]);

            $this->createFollowNotification->handle($follower, $followed);
        });
    }
}
