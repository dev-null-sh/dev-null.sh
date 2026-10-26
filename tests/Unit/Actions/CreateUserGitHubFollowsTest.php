<?php

declare(strict_types=1);

use App\Actions\CreateUserGitHubFollows;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('follows the users they follow on github', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $notFollowed = User::factory()->create();

    fakeGitHubFollows($user, following: [$followed]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->isFollowing($followed))->toBeTrue()
        ->and($user->isFollowing($notFollowed))->toBeFalse()
        ->and($user->refresh()->github_follows_imported_at)->not->toBeNull();
});

it('has the users following them on github follow them', function (): void {
    $user = User::factory()->create();
    $follower = User::factory()->create();

    fakeGitHubFollows($user, followers: [$follower]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($follower->isFollowing($user))->toBeTrue()
        ->and($user->isFollowing($follower))->toBeFalse();
});

it('ignores github accounts that are not on devnull', function (): void {
    $user = User::factory()->create();
    $stranger = User::factory()->make(['github_id' => '999']);

    fakeGitHubFollows($user, following: [$stranger], followers: [$stranger]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->following()->count())->toBe(0)
        ->and($user->followers()->count())->toBe(0);
});

it('notifies about every new follow', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $follower = User::factory()->create();

    fakeGitHubFollows($user, following: [$followed], followers: [$follower]);

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

    fakeGitHubFollows($user, following: [$followed], followers: [$follower]);

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

    fakeGitHubFollows($user, following: [$alreadyFollowed]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->isFollowing($alreadyFollowed))->toBeTrue()
        ->and($user->isFollowing($notOnGitHub))->toBeTrue()
        ->and(Notification::query()->count())->toBe(0);
});

it('reads every page of the follows', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $strangers = array_map(fn (int $id): array => ['id' => $id, 'login' => "stranger-{$id}"], range(1, 100));

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->handle]),
        "api.github.com/users/{$user->handle}/following*" => Http::sequence()
            ->push($strangers)
            ->push([['id' => (int) $followed->github_id, 'login' => $followed->handle]]),
        "api.github.com/users/{$user->handle}/followers*" => Http::response([]),
    ]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->isFollowing($followed))->toBeTrue();
});

it('imports the follows of a user only once', function (): void {
    $user = User::factory()->create(['github_follows_imported_at' => now()]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    Http::assertNothingSent();
});

it('marks the follows as imported when the github account no longer exists', function (): void {
    $user = User::factory()->create();

    Http::fake(["api.github.com/user/{$user->github_id}" => Http::response(['message' => 'Not Found'], 404)]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->refresh()->github_follows_imported_at)->not->toBeNull();
});

it('leaves the follows to import again when github fails', function (): void {
    $user = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->handle]),
        '*' => Http::response(['message' => 'Server Error'], 500),
    ]);

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

    fakeGitHubFollows($user, following: [$blocked, $blocker, $allowed], followers: [$blocked, $blocker, $allowed]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->following()->pluck('users.id')->all())->toBe([$allowed->id]);
    expect($user->followers()->pluck('users.id')->all())->toBe([$allowed->id]);
    expect(Notification::query()->where('actor_id', $blocked->id)->orWhere('recipient_id', $blocked->id)->exists())->toBeFalse();
    expect(Notification::query()->where('actor_id', $blocker->id)->orWhere('recipient_id', $blocker->id)->exists())->toBeFalse();
    expect(Notification::query()->count())->toBe(2);
    expect($user->refresh()->github_follows_imported_at)->not->toBeNull();
});
