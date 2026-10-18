<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateRepo
{
    /**
     * The creator maintains the repo.
     */
    public function handle(User $maintainer, string $name, string $slug, ?string $description): Repo
    {
        return DB::transaction(function () use ($maintainer, $name, $slug, $description): Repo {
            $repo = Repo::query()->create(['name' => $name, 'slug' => $slug, 'description' => $description]);

            RepoContributor::query()->create([
                'repo_id' => $repo->id,
                'user_id' => $maintainer->id,
                'role' => RepoRole::Maintainer,
            ]);

            return $repo;
        });
    }
}
