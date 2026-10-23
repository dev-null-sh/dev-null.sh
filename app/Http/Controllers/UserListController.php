<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserList;
use App\Actions\DeleteUserList;
use App\Http\Requests\CreateUserListRequest;
use App\Http\Requests\DeleteUserListRequest;
use App\Http\Requests\ShowUserListRequest;
use App\Models\User;
use App\Models\UserList;
use App\Queries\UserListCommitsQuery;
use Closure;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserListController
{
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('user-list/index', [
            // Names are encrypted, so they are sorted here rather than by the database.
            'lists' => fn (): Collection => $user->lists()->withCount(['members' => $this->visibleMembers($user)])->get()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'maxLists' => UserList::MAX_PER_USER,
            'maxPinned' => UserList::MAX_PINNED,
        ]);
    }

    public function store(CreateUserListRequest $request, #[CurrentUser] User $user, CreateUserList $action): RedirectResponse
    {
        $list = $action->handle($user, $request->name());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Created {$list->name}. Add developers to it from their profiles.",
        ]);

        return back();
    }

    public function show(ShowUserListRequest $request, #[CurrentUser] User $user, UserList $userList, UserListCommitsQuery $commits): Response
    {
        return Inertia::render('user-list/show', [
            'list' => $userList->loadCount(['members' => $this->visibleMembers($user)]),
            'members' => fn (): Collection => $userList->members()->whereNotIn('users.id', $user->idsAcrossBlocks())->orderBy('username')->get(),
            'pinnedLists' => $user->pinnedLists(...),
            'commits' => Inertia::scroll(fn () => $commits->builder($user, $userList)->cursorPaginate(20)),
        ]);
    }

    public function destroy(DeleteUserListRequest $request, UserList $userList, DeleteUserList $action): RedirectResponse
    {
        $action->handle($userList);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Deleted {$userList->name}."]);

        return to_route('user-list.index');
    }

    /**
     * Someone across a block stays on the list, but its owner no longer sees or counts them there, as anywhere else.
     *
     * @return Closure(Builder<User>): Builder<User>
     */
    private function visibleMembers(User $user): Closure
    {
        return fn (Builder $query): Builder => $query->whereNotIn('users.id', $user->idsAcrossBlocks());
    }
}
