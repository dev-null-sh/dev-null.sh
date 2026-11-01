<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserBadge;
use App\Http\Requests\ShowUserBadgeRequest;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class UserBadgeController
{
    public function show(ShowUserBadgeRequest $request, User $user, CreateUserBadge $action): Response
    {
        return response($action->handle($user, $request->theme()), headers: [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}
