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
        // Another of the user's pages may have remembered it already, and announcing it again would only make every page
        // read it again.
        if ($user->log !== $request->log()) {
            $action->handle($user, $request->log());
        }

        return response()->noContent();
    }
}
