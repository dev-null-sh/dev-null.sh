<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateRepoContributors;
use App\Actions\DeleteRepoContributor;
use App\Actions\UpdateRepoContributorRole;
use App\Enums\RepoRole;
use App\Http\Requests\CreateRepoContributorRequest;
use App\Http\Requests\DeleteRepoContributorRequest;
use App\Http\Requests\UpdateRepoContributorRequest;
use App\Models\Repo;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class RepoContributorController
{
    public function store(CreateRepoContributorRequest $request, Repo $repo, CreateRepoContributors $action): RedirectResponse
    {
        $action->handle($repo, $request->contributors());

        return back();
    }

    public function update(UpdateRepoContributorRequest $request, Repo $repo, User $contributor, UpdateRepoContributorRole $action): RedirectResponse
    {
        $role = $request->role();
        assert($role instanceof RepoRole);

        $action->handle($repo, $contributor, $role);

        return back();
    }

    /**
     * Leaving takes you back to your repos, since this one is no longer yours to see.
     */
    public function destroy(DeleteRepoContributorRequest $request, #[CurrentUser] User $user, Repo $repo, User $contributor, DeleteRepoContributor $action): RedirectResponse
    {
        $action->handle($repo, $contributor);

        return $user->is($contributor) ? to_route('repo.index') : back();
    }
}
