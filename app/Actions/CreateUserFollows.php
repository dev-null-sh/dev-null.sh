<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserFollows
{
    public function __construct(private CreateFollowNotification $createFollowNotification)
    {
        //
    }

    /**
     * @param  Collection<int, User>  $people
     */
    public function handle(User $follower, Collection $people): int
    {
        return DB::transaction(function () use ($follower, $people): int {
            $count = 0;

            foreach ($people as $person) {
                $count += $follower->following()->newPivotStatement()->insertOrIgnore([
                    'follower_id' => $follower->id,
                    'followed_id' => $person->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->createFollowNotification->handle($follower, $person);
            }

            return $count;
        });
    }
}
