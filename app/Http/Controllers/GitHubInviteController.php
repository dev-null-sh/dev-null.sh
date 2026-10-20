<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\GitHubInviteesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class GitHubInviteController
{
    /**
     * The GitHub list is deferred, so a slow GitHub never holds up the page.
     */
    public function index(#[CurrentUser] User $user, GitHubInviteesQuery $invitees): Response
    {
        return Inertia::render('invite/index', [
            'invitees' => Inertia::defer(fn (): ?array => $invitees->get($user)),
        ]);
    }
}
