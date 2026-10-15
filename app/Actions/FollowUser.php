<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class FollowUser
{
    public function __construct(private NotifyAboutFollow $notifyAboutFollow)
    {
        //
    }

    public function handle(User $follower, User $followed): void
    {
        throw_if($follower->is($followed), InvalidArgumentException::class, 'Users cannot follow themselves.');
        throw_if($follower->hasBlockWith($followed), InvalidArgumentException::class, 'Users cannot follow someone they block or who blocks them.');

        DB::transaction(function () use ($follower, $followed): void {
            $follower->following()->syncWithoutDetaching([$followed->id]);

            $this->notifyAboutFollow->handle($follower, $followed);
        });
    }
}
