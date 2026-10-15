<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class CreateUserFollow
{
    public function __construct(private CreateUserFollows $createUserFollows)
    {
        //
    }

    public function handle(User $follower, User $followed): void
    {
        $this->createUserFollows->handle($follower, new Collection([$followed]));
    }
}
