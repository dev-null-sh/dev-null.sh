<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserLog;
use App\Http\Requests\UpdateUserLogRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserLogController
{
    public function update(UpdateUserLogRequest $request, #[CurrentUser] User $user, UpdateUserLog $action): JsonResponse
    {
        if ($user->log !== $request->log()) {
            $action->handle($user, $request->log());
        }

        return response()->json(['log' => $request->log()]);
    }
}
