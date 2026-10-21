<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\StarredCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class StarredCommitController
{
    public function index(#[CurrentUser] User $user, StarredCommitsQuery $starred): Response
    {
        return Inertia::render('starred/index', [
            'commits' => Inertia::scroll(fn () => $starred->builder($user)->cursorPaginate(20)),
        ]);
    }
}
