<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserListMember;
use App\Actions\DeleteUserListMember;
use App\Http\Requests\CreateUserListMemberRequest;
use App\Http\Requests\DeleteUserListMemberRequest;
use App\Models\User;
use App\Models\UserList;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserListMemberController
{
    public function store(CreateUserListMemberRequest $request, UserList $userList, User $user, CreateUserListMember $action): RedirectResponse
    {
        $action->handle($userList, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added @{$user->handle} to {$userList->name}."]);

        return back();
    }

    public function destroy(DeleteUserListMemberRequest $request, UserList $userList, User $user, DeleteUserListMember $action): RedirectResponse
    {
        $action->handle($userList, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Removed @{$user->handle} from {$userList->name}."]);

        return back();
    }
}
