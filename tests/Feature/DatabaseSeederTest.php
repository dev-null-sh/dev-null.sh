<?php

declare(strict_types=1);

use App\Enums\InboxTab;
use App\Enums\RepoRole;
use App\Models\RepoContributor;
use App\Models\User;
use App\Queries\ConversationsQuery;
use Database\Seeders\DatabaseSeeder;

it('gives the local developer people to follow and followers', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();

    expect($localDeveloper->username)->toBe('local-dev')
        ->and($localDeveloper->following()->count())->toBe(3)
        ->and($localDeveloper->followers()->count())->toBe(5);
});

it('reuses an existing local developer', function (): void {
    $localDeveloper = User::factory()->create(['github_id' => 'local']);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('github_id', 'local')->sole()->id)->toBe($localDeveloper->id)
        ->and($localDeveloper->following()->count())->toBe(3)
        ->and($localDeveloper->followers()->count())->toBe(5);
});

it('gives the local developer conversations to read and reply to', function (bool $requests, int $fetched, int $unread): void {
    config(['remotes.requests' => $requests]);

    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();
    $conversations = resolve(ConversationsQuery::class);

    expect($conversations->get($localDeveloper)['conversations'])->toHaveCount(4)
        ->and($conversations->get($localDeveloper, InboxTab::Fetched)['conversations'])->toHaveCount($fetched)
        ->and($conversations->unreadCount($localDeveloper))->toBe($unread);
})->with([
    'requests off' => [false, 0, 3],
    'requests on' => [true, 1, 4],
]);

it('gives the local developer a repo they maintain with two of their followers', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();
    $repo = $localDeveloper->repos()->where('slug', 'dev-null')->sole();
    $contributors = $repo->contributors()->where('user_id', '!=', $localDeveloper->id)->get();

    expect($repo->isMaintainedBy($localDeveloper))->toBeTrue()
        ->and($contributors)->toHaveCount(2)
        ->and($contributors->every(fn (RepoContributor $contributor): bool => $contributor->user->isFollowing($localDeveloper)))->toBeTrue()
        ->and($contributors->map(fn (RepoContributor $contributor): string => $contributor->role->value)->all())->toEqualCanonicalizing(['contributor', 'maintainer'])
        ->and($repo->commits()->count())->toBe(7)
        ->and($repo->commits()->whereNotNull('parent_id')->count())->toBe(3)
        ->and($repo->commits()->whereNotNull('quoted_commit_id')->count())->toBe(1);
});

it('gives the local developer a repo they only contribute to', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();
    $repo = $localDeveloper->repos()->where('slug', 'design-crit')->sole();
    $maintainer = $repo->contributors()->where('role', RepoRole::Maintainer)->sole()->user;

    expect($repo->roleOf($localDeveloper))->toBe(RepoRole::Contributor)
        ->and($localDeveloper->isFollowing($maintainer))->toBeTrue()
        ->and($repo->commits()->count())->toBe(2);
});
