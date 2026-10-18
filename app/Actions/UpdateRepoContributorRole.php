<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;

final readonly class UpdateRepoContributorRole
{
    public function handle(Repo $repo, User $contributor, RepoRole $role): void
    {
        RepoContributor::query()
            ->where('repo_id', $repo->id)
            ->where('user_id', $contributor->id)
            ->update(['role' => $role]);
    }
}
