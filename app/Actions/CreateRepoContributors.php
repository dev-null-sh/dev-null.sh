<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class CreateRepoContributors
{
    /**
     * Everyone added joins as a contributor; maintainers promote them from there.
     *
     * @param  Collection<int, User>  $contributors
     */
    public function handle(Repo $repo, Collection $contributors): void
    {
        DB::transaction(function () use ($repo, $contributors): void {
            foreach ($contributors as $contributor) {
                RepoContributor::query()->create([
                    'repo_id' => $repo->id,
                    'user_id' => $contributor->id,
                    'role' => RepoRole::Contributor,
                ]);
            }
        });
    }
}
