<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteUserListPin;
use App\Actions\UpdateUserListPin;
use App\Http\Requests\DeleteUserListPinRequest;
use App\Http\Requests\UpdateUserListPinRequest;
use App\Models\UserList;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserListPinController
{
    public function update(UpdateUserListPinRequest $request, UserList $userList, UpdateUserListPin $action): RedirectResponse
    {
        $action->handle($userList);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pinned {$userList->name} next to your logs."]);

        return back();
    }

    public function destroy(DeleteUserListPinRequest $request, UserList $userList, DeleteUserListPin $action): RedirectResponse
    {
        $action->handle($userList);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Unpinned {$userList->name}."]);

        return back();
    }
}
