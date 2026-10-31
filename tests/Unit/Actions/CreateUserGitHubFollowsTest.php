<?php

declare(strict_types=1);

use App\Actions\CreateUserGitHubFollows;
use App\Contracts\Services\GitHubProvider;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

it('follows the users they follow on github', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $notFollowed = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([$followed->github_id]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->isFollowing($followed))->toBeTrue()
        ->and($user->isFollowing($notFollowed))->toBeFalse()
        ->and($user->refresh()->github_follows_imported_at)->not->toBeNull();
});

it('has the users following them on github follow them', function (): void {
    $user = User::factory()->create();
    $follower = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([$follower->github_id]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($follower->isFollowing($user))->toBeTrue()
        ->and($user->isFollowing($follower))->toBeFalse();
});

it('ignores github accounts that are not on devnull', function (): void {
    $user = User::factory()->create();
    $stranger = User::factory()->make(['github_id' => '999']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([$stranger->github_id]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([$stranger->github_id]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->following()->count())->toBe(0)
        ->and($user->followers()->count())->toBe(0);
});

it('notifies about every new follow', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $follower = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([$followed->github_id]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([$follower->github_id]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect(Notification::query()->where('type', NotificationType::Followed)->get())
        ->toHaveCount(2)
        ->sequence(
            fn ($notification) => $notification->recipient_id->toBe($followed->id)->actor_id->toBe($user->id),
            fn ($notification) => $notification->recipient_id->toBe($user->id)->actor_id->toBe($follower->id),
        );
});

it('notifies nobody when told not to', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $follower = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([$followed->github_id]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([$follower->github_id]);

    resolve(CreateUserGitHubFollows::class)->handle($user, notify: false);

    expect($user->isFollowing($followed))->toBeTrue()
        ->and($follower->isFollowing($user))->toBeTrue()
        ->and(Notification::query()->count())->toBe(0);
});

it('never unfollows anyone, and does not notify about follows that already exist', function (): void {
    $user = User::factory()->create();
    $alreadyFollowed = User::factory()->create();
    $notOnGitHub = User::factory()->create();

    $user->following()->attach([$alreadyFollowed->id, $notOnGitHub->id]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([$alreadyFollowed->github_id]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->isFollowing($alreadyFollowed))->toBeTrue()
        ->and($user->isFollowing($notOnGitHub))->toBeTrue()
        ->and(Notification::query()->count())->toBe(0);
});

it('imports the follows of a user only once', function (): void {
    $user = User::factory()->create(['github_follows_imported_at' => now()]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->never();

    resolve(CreateUserGitHubFollows::class)->handle($user);
});

it('marks the follows as imported when the github account no longer exists', function (): void {
    $user = User::factory()->create();

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->refresh()->github_follows_imported_at)->not->toBeNull();
});

it('leaves the follows to import again when github fails', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->andThrow(new RequestException(new Response(new Psr7Response(500))));

    expect(fn () => resolve(CreateUserGitHubFollows::class)->handle($user))->toThrow(RequestException::class)
        ->and($user->refresh()->github_follows_imported_at)->toBeNull();
});

it('keeps github imports from following across a block in either direction', function (): void {
    $user = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $allowed = User::factory()->create();

    $user->blocking()->attach($blocked);
    $blocker->blocking()->attach($user);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn([$blocked->github_id, $blocker->github_id, $allowed->github_id]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([$blocked->github_id, $blocker->github_id, $allowed->github_id]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->following()->pluck('users.id')->all())->toBe([$allowed->id]);
    expect($user->followers()->pluck('users.id')->all())->toBe([$allowed->id]);
    expect(Notification::query()->where('actor_id', $blocked->id)->orWhere('recipient_id', $blocked->id)->exists())->toBeFalse();
    expect(Notification::query()->where('actor_id', $blocker->id)->orWhere('recipient_id', $blocker->id)->exists())->toBeFalse();
    expect(Notification::query()->count())->toBe(2);
    expect($user->refresh()->github_follows_imported_at)->not->toBeNull();
});
