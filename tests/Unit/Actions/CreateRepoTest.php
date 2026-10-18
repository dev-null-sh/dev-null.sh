<?php

declare(strict_types=1);

use App\Actions\CreateRepo;
use App\Enums\RepoRole;
use App\Models\User;

it('creates a repo maintained by its creator', function (): void {
    $maintainer = User::factory()->create();

    $repo = resolve(CreateRepo::class)->handle($maintainer, 'dev/null', 'dev-null', 'The social network for developers.');

    expect($repo->name)->toBe('dev/null')
        ->and($repo->slug)->toBe('dev-null')
        ->and($repo->description)->toBe('The social network for developers.')
        ->and($repo->contributors()->sole()->user_id)->toBe($maintainer->id)
        ->and($repo->roleOf($maintainer))->toBe(RepoRole::Maintainer);
});

it('creates a repo without a description', function (): void {
    $repo = resolve(CreateRepo::class)->handle(User::factory()->create(), 'dev/null', 'dev-null', null);

    expect($repo->refresh()->description)->toBeNull();
});
