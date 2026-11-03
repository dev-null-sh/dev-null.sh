<?php

declare(strict_types=1);

use App\Actions\CreateUserGitHubFollows;
use App\Contracts\Services\GitHubProvider;
use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('follows the users they follow on github', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $notFollowed = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [$followed->github_id], 'organizations' => []]);
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
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [], 'organizations' => []]);
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
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [$stranger->github_id], 'organizations' => []]);
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
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [$followed->github_id], 'organizations' => []]);
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
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [$followed->github_id], 'organizations' => []]);
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
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [$alreadyFollowed->github_id], 'organizations' => []]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->isFollowing($alreadyFollowed))->toBeTrue()
        ->and($user->isFollowing($notOnGitHub))->toBeTrue()
        ->and(Notification::query()->count())->toBe(0);
});

it('imports the follows of a user only once', function (): void {
    $user = User::factory()->create(['github_follows_imported_at' => now(), 'github_organization_follows_imported_at' => now()]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->never();

    resolve(CreateUserGitHubFollows::class)->handle($user);
});

it('marks the follows as imported when the github account no longer exists', function (): void {
    $user = User::factory()->create();

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->refresh())
        ->github_follows_imported_at->not->toBeNull()
        ->github_organization_follows_imported_at->not->toBeNull();
});

it('leaves the follows to import again when github fails', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->andThrow(new RequestException(new Response(new Psr7Response(500))));

    expect(fn () => resolve(CreateUserGitHubFollows::class)->handle($user))->toThrow(RequestException::class)
        ->and($user->refresh()->github_follows_imported_at)->toBeNull()
        ->and($user->github_organization_follows_imported_at)->toBeNull();
});

it('follows the organizations they follow on github, and keeps the ones not on devnull for when they are', function (): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    Organization::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [], 'organizations' => [$organization->profile->github_id, '958072']]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->followedOrganizations()->pluck('organizations.id')->all())->toBe([$organization->id])
        ->and(DB::table('pending_organization_follows')->get(['user_id', 'github_id'])->map(fn (object $row): array => (array) $row)->all())->toBe([['user_id' => $user->id, 'github_id' => '958072']])
        ->and($user->refresh()->github_organization_follows_imported_at)->not->toBeNull()
        ->and(Notification::query()->count())->toBe(0);
});

it('imports only the organizations of a user whose people were imported before, so unfollows stick', function (): void {
    $importedAt = now()->subWeek()->startOfSecond();
    $user = User::factory()->create(['github_follows_imported_at' => $importedAt]);
    $unfollowed = User::factory()->create();
    $organization = Organization::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [$unfollowed->github_id], 'organizations' => [$organization->profile->github_id]]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->isFollowing($unfollowed))->toBeFalse()
        ->and($user->followedOrganizations()->pluck('organizations.id')->all())->toBe([$organization->id])
        ->and($user->refresh()->github_follows_imported_at->toDateTimeString())->toBe($importedAt->toDateTimeString())
        ->and($user->github_organization_follows_imported_at)->not->toBeNull();

    $github->shouldNotHaveReceived('followerIds');
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
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [$blocked->github_id, $blocker->github_id, $allowed->github_id], 'organizations' => []]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([$blocked->github_id, $blocker->github_id, $allowed->github_id]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    expect($user->following()->pluck('users.id')->all())->toBe([$allowed->id]);
    expect($user->followers()->pluck('users.id')->all())->toBe([$allowed->id]);
    expect(Notification::query()->where('actor_id', $blocked->id)->orWhere('recipient_id', $blocked->id)->exists())->toBeFalse();
    expect(Notification::query()->where('actor_id', $blocker->id)->orWhere('recipient_id', $blocker->id)->exists())->toBeFalse();
    expect(Notification::query()->count())->toBe(2);
    expect($user->refresh()->github_follows_imported_at)->not->toBeNull();
});

it('tells everyone whose follows it changed, even importing quietly, as following one by one would', function (): void {
    Event::fake([UsersUpdated::class, ProfilesUpdated::class]);
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $alreadyFollowed = User::factory()->create();
    $follower = User::factory()->create();
    $organization = Organization::factory()->create();
    $user->following()->attach($alreadyFollowed);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [$followed->github_id, $alreadyFollowed->github_id], 'organizations' => [$organization->profile->github_id]]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([$follower->github_id]);

    resolve(CreateUserGitHubFollows::class)->handle($user, notify: false);

    $usersUpdated = fn (UserUpdate $update): array => Event::dispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === $update)
        ->flatMap(fn (array $event): array => $event[0]->userIds)->unique()->sort()->values()->all();
    $profiles = Event::dispatched(ProfilesUpdated::class, fn (ProfilesUpdated $event): bool => $event->changesConnections)
        ->flatMap(fn (array $event): array => $event[0]->profileIds)->unique()->sort()->values()->all();
    $sorted = fn (string ...$ids): array => collect($ids)->sort()->values()->all();

    expect($usersUpdated(UserUpdate::Account))->toBe($sorted($user->id, $followed->id, $follower->id))
        ->and($usersUpdated(UserUpdate::Relationships))->toBe($sorted($user->id, $follower->id))
        ->and($profiles)->toBe($sorted($user->id, $followed->id, $follower->id, $organization->id));
});

it('tells nobody but the user about an import that followed no one', function (): void {
    Event::fake([UsersUpdated::class, ProfilesUpdated::class]);
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('followingIds')->with($user->handle, 10)->andReturn(['users' => [], 'organizations' => []]);
    $github->shouldReceive('followerIds')->with($user->handle, 10)->andReturn([]);

    resolve(CreateUserGitHubFollows::class)->handle($user);

    Event::assertNotDispatched(ProfilesUpdated::class);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Onboarding);
    Event::assertNotDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => in_array($event->update, [UserUpdate::Account, UserUpdate::Relationships], true));
});
