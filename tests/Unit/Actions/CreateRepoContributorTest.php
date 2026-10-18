<?php

declare(strict_types=1);

use App\Actions\CreateRepoContributor;
use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\User;

it('adds a contributor to a repo', function (): void {
    $repo = Repo::factory()->create();
    $contributor = User::factory()->create();

    resolve(CreateRepoContributor::class)->handle($repo, $contributor);

    expect($repo->roleOf($contributor))->toBe(RepoRole::Contributor)
        ->and($contributor->repos->modelKeys())->toBe([$repo->id]);
});
