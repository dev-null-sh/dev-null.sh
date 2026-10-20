<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\SavedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SavedCommitController
{
    public function __invoke(#[CurrentUser] User $user, SavedCommitsQuery $saved): Response
    {
        return Inertia::render('saved/index', [
            'commits' => Inertia::scroll(fn () => $saved->builder($user)->cursorPaginate(20)),
        ]);
    }
}
