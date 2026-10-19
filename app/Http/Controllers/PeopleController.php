<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListPeopleRequest;
use App\Models\User;
use App\Queries\SuggestedUsersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PeopleController
{
    public function index(ListPeopleRequest $request, #[CurrentUser] User $user, SuggestedUsersQuery $suggestions): Response
    {
        return Inertia::render('people/index', [
            'users' => Inertia::scroll(fn () => $suggestions->builder($user)->latest('id')->cursorPaginate(30)),
        ]);
    }
}
