<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateGitHubDiscovery;
use App\Http\Requests\CreateGitHubPeopleRequest;
use App\Http\Requests\ListGitHubPeopleRequest;
use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class GitHubPeopleController
{
    public function index(ListGitHubPeopleRequest $request, #[CurrentUser] User $user, GitHubPeopleQuery $query): Response
    {
        return Inertia::render('github-people/index', [
            'discovery' => fn (): array => $query->get($user),
        ]);
    }

    public function store(CreateGitHubPeopleRequest $request, #[CurrentUser] User $user, CreateGitHubDiscovery $action): RedirectResponse
    {
        $action->handle($user);

        return to_route('github-people.index');
    }
}
