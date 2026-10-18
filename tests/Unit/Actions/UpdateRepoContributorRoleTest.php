<?php

declare(strict_types=1);

use App\Actions\UpdateRepoContributorRole;
use App\Enums\RepoRole;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;

it('promotes a contributor to maintainer', function (): void {
    $repo = Repo::factory()->create();
    $contributor = RepoContributor::factory()->for($repo)->create()->user;

    resolve(UpdateRepoContributorRole::class)->handle($repo, $contributor, RepoRole::Maintainer);

    expect($repo->isMaintainedBy($contributor))->toBeTrue();
});

it('demotes a maintainer to contributor', function (): void {
    $repo = Repo::factory()->create();
    RepoContributor::factory()->for($repo)->maintainer()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;

    resolve(UpdateRepoContributorRole::class)->handle($repo, $maintainer, RepoRole::Contributor);

    expect($repo->roleOf($maintainer))->toBe(RepoRole::Contributor);
});

it('leaves other repos alone', function (): void {
    $repo = Repo::factory()->create();
    $contributor = RepoContributor::factory()->for($repo)->create()->user;
    $other = RepoContributor::factory()->for($contributor)->create()->repo;

    resolve(UpdateRepoContributorRole::class)->handle($repo, $contributor, RepoRole::Maintainer);

    expect($other->roleOf($contributor))->toBe(RepoRole::Contributor);
});

it('adds nobody who is outside the repo', function (): void {
    $repo = Repo::factory()->create();

    resolve(UpdateRepoContributorRole::class)->handle($repo, User::factory()->create(), RepoRole::Maintainer);

    expect($repo->contributors()->count())->toBe(0);
});
