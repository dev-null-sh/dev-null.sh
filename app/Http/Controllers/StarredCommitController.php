<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ShownStreams;
use App\Models\User;
use App\Queries\StarredCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class StarredCommitController
{
    public function index(#[CurrentUser] User $user, StarredCommitsQuery $starred): Response
    {
        return Inertia::render('starred/index', [
            'commits' => Inertia::scroll(fn (): CursorPaginator => ShownStreams::page($starred->builder($user)->cursorPaginate(20))),
        ]);
    }
}
