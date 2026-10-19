<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateRepo;
use App\Http\Requests\CreateRepoRequest;
use App\Http\Requests\ShowRepoRequest;
use App\Models\Commit;
use App\Models\Repo;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class RepoController
{
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('repo/index', [
            'repos' => $user->repos()->withCount('contributors')->orderBy('name')->get(),
        ]);
    }

    public function store(CreateRepoRequest $request, #[CurrentUser] User $user, CreateRepo $action): RedirectResponse
    {
        $repo = $action->handle($user, $request->name(), $request->slug(), $request->description());

        return to_route('repo.show', $repo);
    }

    public function show(ShowRepoRequest $request, #[CurrentUser] User $user, Repo $repo): Response
    {
        return Inertia::render('repo/show', [
            'repo' => $repo,
            'role' => $repo->roleOf($user),
            'contributors' => fn (): Collection => $repo->contributors()->with('user')->oldest('id')->get(),
            'commits' => Inertia::scroll(fn () => Commit::query()
                ->inRepo($repo)
                ->visibleTo($user)
                ->topLevel()
                ->withVisibleReplyCount($user)
                ->withReplyPreviews($user)
                ->withCardDataFor($user)
                ->newestFirst()
                ->cursorPaginate(20)),
        ]);
    }
}
