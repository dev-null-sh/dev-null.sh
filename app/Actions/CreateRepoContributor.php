<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;

final readonly class CreateRepoContributor
{
    /**
     * Everyone added joins as a contributor; maintainers promote them from there.
     */
    public function handle(Repo $repo, User $contributor): void
    {
        RepoContributor::query()->create([
            'repo_id' => $repo->id,
            'user_id' => $contributor->id,
            'role' => RepoRole::Contributor,
        ]);
    }
}
