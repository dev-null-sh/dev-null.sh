<?php

declare(strict_types=1);

use App\Enums\RepoRole;
use App\Models\Commit;
use App\Models\Repo;
use App\Models\RepoContributor;
use App\Models\User;

test('to array', function (): void {
    $repo = Repo::factory()->create()->refresh();

    expect(array_keys($repo->toArray()))
        ->toBe([
            'id',
            'name',
            'slug',
            'description',
            'created_at',
            'updated_at',
        ]);
});

it('has contributors', function (): void {
    $repo = Repo::factory()->create();
    $contributor = RepoContributor::factory()->for($repo)->create();
    RepoContributor::factory()->create();

    expect($repo->contributors->modelKeys())->toBe([$contributor->id])
        ->and($contributor->repo->is($repo))->toBeTrue()
        ->and($contributor->user->is(User::query()->findOrFail($contributor->user_id)))->toBeTrue();
});

it('has commits', function (): void {
    $repo = Repo::factory()->create();
    $commit = Commit::factory()->for($repo)->create();
    Commit::factory()->create();

    expect($repo->commits->modelKeys())->toBe([$commit->id])
        ->and($commit->repo?->is($repo))->toBeTrue();
});

it('knows the role of a user', function (): void {
    $repo = Repo::factory()->create();
    $maintainer = RepoContributor::factory()->for($repo)->maintainer()->create()->user;
    $contributor = RepoContributor::factory()->for($repo)->create()->user;
    $stranger = User::factory()->create();

    expect($repo->roleOf($maintainer))->toBe(RepoRole::Maintainer)
        ->and($repo->roleOf($contributor))->toBe(RepoRole::Contributor)
        ->and($repo->roleOf($stranger))->toBeNull()
        ->and($repo->isMaintainedBy($maintainer))->toBeTrue()
        ->and($repo->isMaintainedBy($contributor))->toBeFalse()
        ->and($repo->isMaintainedBy($stranger))->toBeFalse();
});

it('deletes its contributors and commits with it', function (): void {
    $repo = Repo::factory()->create();
    RepoContributor::factory()->for($repo)->create();
    Commit::factory()->for($repo)->create();

    $repo->delete();

    expect(RepoContributor::query()->count())->toBe(0)
        ->and(Commit::query()->withTrashed()->count())->toBe(0);
});

it('leaves the repo when a contributor is deleted', function (): void {
    $contributor = RepoContributor::factory()->create();

    $contributor->user->delete();

    expect(RepoContributor::query()->count())->toBe(0)
        ->and(Repo::query()->count())->toBe(1);
});
