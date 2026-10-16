<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserFeed;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class UserFeedController
{
    public function show(User $user, CreateUserFeed $action): Response
    {
        return response($action->handle($user), headers: [
            'Content-Type' => 'application/atom+xml; charset=UTF-8',
        ]);
    }
}
