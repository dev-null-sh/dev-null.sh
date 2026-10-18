<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteRepoContributor
{
    /**
     * Removes a contributor, or lets them leave. They lose access to the repo and its commits. When the last maintainer
     * goes, the longest-standing contributor takes over, and a repo nobody is left in is deleted, commits and all.
     */
    public function handle(Repo $repo, User $contributor): void
    {
        DB::transaction(function () use ($repo, $contributor): void {
            RepoContributor::query()->where('repo_id', $repo->id)->where('user_id', $contributor->id)->delete();

            $remaining = $repo->contributors()->oldest('id')->get();

            if ($remaining->isEmpty()) {
                $repo->delete();

                return;
            }

            if ($remaining->doesntContain(fn (RepoContributor $remainder): bool => $remainder->role === RepoRole::Maintainer)) {
                $remaining->firstOrFail()->update(['role' => RepoRole::Maintainer]);
            }
        });
    }
}
