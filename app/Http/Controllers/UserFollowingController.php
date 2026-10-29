<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConnectionType;
use App\Http\Requests\ListUserFollowingRequest;
use App\Models\Organization;
use App\Models\User;
use App\Queries\FollowedOrganizationsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserFollowingController
{
    public function index(ListUserFollowingRequest $request, #[CurrentUser] User $currentUser, User $user, FollowedOrganizationsQuery $organizations): Response
    {
        return Inertia::render('user/connections', [
            'user' => $user,
            'type' => ConnectionType::Following,
            'users' => Inertia::scroll(fn () => $user->following()
                ->withExists(['followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($currentUser->id)])
                ->latest('follows.created_at')
                ->latest('users.id')
                ->cursorPaginate(30)),
            // Above the people, a few at a time on demand, with a cursor of their own.
            'organizations' => Inertia::scroll(function () use ($organizations, $user, $currentUser): CursorPaginator {
                $page = $organizations->builder($user, $currentUser)->cursorPaginate(10, cursorName: 'organizations_cursor');

                collect($page->items())->each(fn (Organization $organization): Organization => $organization->makeVisible(['id', 'is_followed']));

                return $page;
            }),
        ]);
    }
}
