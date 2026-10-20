<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserLog;
use App\Http\Requests\UpdateUserLogRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class UserLogController
{
    public function update(UpdateUserLogRequest $request, #[CurrentUser] User $user, UpdateUserLog $action): Response
    {
        $action->handle($user, $request->log());

        return response()->noContent();
    }
}
