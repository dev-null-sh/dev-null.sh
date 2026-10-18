<?php

declare(strict_types=1);

use App\Actions\DeleteRepoContributor;
use App\Enums\RepoRole;
use App\Models\Commit;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;

it('removes a contributor, who loses access to the repo commits', function (): void {
    $repo = Repo::factory()->create();
    RepoContributor::factory()->for($repo)->maintainer()->create();
    $contributor = RepoContributor::factory()->for($repo)->create()->user;
    $commit = Commit::factory()->for($repo)->create();

    resolve(DeleteRepoContributor::class)->handle($repo, $contributor);

    expect($repo->hasContributor($contributor))->toBeFalse()
        ->and($repo->contributors()->count())->toBeOne()
        ->and($commit->isVisibleTo($contributor))->toBeFalse();
});

it('lets a maintainer leave while another maintainer stays', function (): void {
    $repo = Repo::factory()->create();
    $leaving = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $staying = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $contributor = RepoContributor::factory()->for($repo)->create()->user;

    resolve(DeleteRepoContributor::class)->handle($repo, $leaving);

    expect($repo->isMaintainedBy($staying))->toBeTrue()
        ->and($repo->roleOf($contributor))->toBe(RepoRole::Contributor);
});

it('hands the repo to the longest-standing contributor when the last maintainer leaves', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $longest = RepoContributor::factory()->for($repo)->create()->user;
    $newest = RepoContributor::factory()->for($repo)->create()->user;

    resolve(DeleteRepoContributor::class)->handle($repo, $maintainer);

    expect($repo->hasContributor($maintainer))->toBeFalse()
        ->and($repo->isMaintainedBy($longest))->toBeTrue()
        ->and($repo->roleOf($newest))->toBe(RepoRole::Contributor);
});

it('deletes the repo and its commits when its last contributor leaves', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    Commit::factory()->for($maintainer)->for($repo)->create();

    resolve(DeleteRepoContributor::class)->handle($repo, $maintainer);

    expect(Repo::query()->count())->toBe(0)
        ->and(Commit::query()->withTrashed()->count())->toBe(0);
});

it('does nothing for someone outside the repo', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;

    resolve(DeleteRepoContributor::class)->handle($repo, User::factory()->create());

    expect($repo->contributors()->count())->toBeOne()
        ->and($repo->isMaintainedBy($maintainer))->toBeTrue();
});
