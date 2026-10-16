<?php

declare(strict_types=1);

use App\Actions\BefriendTom;
use App\Enums\CommitStatus;
use App\Models\User;

it('makes tom and the user follow each other', function (): void {
    $user = User::factory()->create();

    resolve(BefriendTom::class)->handle($user);

    $tom = User::query()->where('username', 'tom')->sole();

    expect($user->isFollowing($tom))->toBeTrue()
        ->and($tom->isFollowing($user))->toBeTrue()
        ->and($user->notifications()->count())->toBe(0)
        ->and($tom->notifications()->count())->toBe(0);
});

it('creates tom once, with a single welcome commit and no github account', function (): void {
    $action = resolve(BefriendTom::class);

    $action->handle(User::factory()->create());
    $action->handle(User::factory()->create());

    $tom = User::query()->where('username', 'tom')->sole();
    $commit = $tom->commits()->sole();

    expect($commit->body)->toBe('Welcome to dev/null!')
        ->and($commit->status)->toBe(CommitStatus::Approved)
        ->and($tom->hasGitHubAccount())->toBeFalse()
        ->and($tom->following()->count())->toBe(2);
});

it('befriends every existing user', function (): void {
    $users = User::factory()->count(3)->create();

    $this->artisan('tom:befriend')->assertSuccessful();

    $tom = User::query()->where('username', 'tom')->sole();

    expect($tom->following()->count())->toBe(3)
        ->and($tom->followers()->count())->toBe(3)
        ->and($tom->isFollowing($tom))->toBeFalse();
});

it('renders tom on his profile without a github link', function (): void {
    resolve(BefriendTom::class)->handle(User::factory()->create());

    $this->get('/@tom')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->where('user.name', 'Tom')
            ->where('user.has_github_account', false));
});
