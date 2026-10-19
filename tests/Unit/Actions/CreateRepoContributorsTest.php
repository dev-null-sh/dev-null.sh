<?php

declare(strict_types=1);

use App\Actions\CreateRepoContributors;
use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\User;

it('adds contributors to a repo', function (): void {
    $repo = Repo::factory()->create();
    [$first, $second] = User::factory()->count(2)->create();

    resolve(CreateRepoContributors::class)->handle($repo, collect([$first, $second]));

    expect($repo->roleOf($first))->toBe(RepoRole::Contributor)
        ->and($repo->roleOf($second))->toBe(RepoRole::Contributor)
        ->and($first->repos->modelKeys())->toBe([$repo->id]);
});
