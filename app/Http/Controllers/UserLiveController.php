<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUserLive;
use App\Actions\UpdateUserLive;
use App\Http\Requests\UpdateUserLiveRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class UserLiveController
{
    public function update(UpdateUserLiveRequest $request, #[CurrentUser] User $user, UpdateUserLive $action): RedirectResponse
    {
        $action->handle($user, $request->platform(), $request->url(), $request->integer('hours'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, DeleteUserLive $action): RedirectResponse
    {
        $action->handle($user);

        return back();
    }
}
