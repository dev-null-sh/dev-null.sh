<?php

declare(strict_types=1);

use App\Enums\InboxTab;
use App\Enums\OrganizationRole;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Queries\ConversationsQuery;
use App\Queries\LogQueries\FollowingLogQuery;
use App\Queries\NotificationGroupsQuery;
use App\Queries\OrganizationMentionsQuery;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Event;

it('gives the local developer people to follow and followers', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();

    expect($localDeveloper->handle)->toBe('local-dev')
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

it('rolls back incomplete seed data so initialization can be retried', function (): void {
    $localDeveloper = User::factory()->create(['github_id' => 'local']);

    Event::listen('eloquent.creating: '.Notification::class, function (): never {
        throw new RuntimeException('Seed interrupted');
    });

    try {
        expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(RuntimeException::class, 'Seed interrupted');
    } finally {
        Event::forget('eloquent.creating: '.Notification::class);
    }

    $this->assertModelExists($localDeveloper);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('profiles', 1);
    $this->assertDatabaseEmpty('commits');
    $this->assertDatabaseEmpty('follows');
    $this->assertDatabaseEmpty('notifications');

    $this->seed(DatabaseSeeder::class);

    expect($localDeveloper->commits()->topLevel()->count())->toBe(4)
        ->and(resolve(ConversationsQuery::class)->get($localDeveloper)['conversations'])->toHaveCount(4);
});

it('gives the local developer separate notifications for replies to one commit', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();
    $replies = $localDeveloper->notifications()->where('type', 'replied')->get();
    $groups = resolve(NotificationGroupsQuery::class)->paginate($localDeveloper, 'replies')->items();

    expect($replies)->toHaveCount(10)
        ->and($replies->pluck('subject_id')->unique())->toHaveCount(1)
        ->and($replies->pluck('commit_id')->unique())->toHaveCount(10)
        ->and($groups)->toHaveCount(10)
        ->and(array_map(fn (Notification $group): int => $group->event_count, $groups))->toBe(array_fill(0, 10, 1));
});

it('gives the local developer GitHub organizations with threads that mention them', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();
    $devNull = Organization::query()->whereRelation('profile', 'handle', 'dev-null-sh')->sole();
    $laravel = Organization::query()->whereRelation('profile', 'handle', 'laravel')->sole();
    $mentions = resolve(OrganizationMentionsQuery::class);
    $devNullMentions = $mentions->builder($devNull, $localDeveloper)->get();

    expect($devNull->profile->only(['github_id', 'avatar_url']))->toBe(['github_id' => '344393722', 'avatar_url' => 'https://avatars.githubusercontent.com/u/344393722?v=4'])
        ->and($laravel->profile->only(['github_id', 'avatar_url']))->toBe(['github_id' => '958072', 'avatar_url' => 'https://avatars.githubusercontent.com/u/958072?v=4'])
        ->and($devNull->roleOf($localDeveloper))->toBe(OrganizationRole::Admin)
        ->and($devNull->members()->count())->toBe(4)
        ->and($devNull->followers()->count())->toBe(5)
        ->and($laravel->roleOf($localDeveloper))->toBeNull()
        ->and($laravel->admins()->count())->toBe(1)
        ->and($laravel->followers()->whereKey($localDeveloper->id)->exists())->toBeTrue()
        ->and($devNullMentions)->toHaveCount(7)
        ->and($devNullMentions->whereNotNull('parent_id'))->toHaveCount(1)
        ->and($devNullMentions->whereNull('parent_id')->every(fn (Commit $commit): bool => $commit->replies_count > 0))->toBeTrue()
        ->and($mentions->builder($laravel, $localDeveloper)->count())->toBe(3);
});

it('gives the GitHub organizations commits of their own, posted by their members', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();
    $devNull = Organization::query()->whereRelation('profile', 'handle', 'dev-null-sh')->sole();
    $laravel = Organization::query()->whereRelation('profile', 'handle', 'laravel')->sole();
    $following = resolve(FollowingLogQuery::class)->builder($localDeveloper)->get();

    expect($devNull->profile->commits()->topLevel()->count())->toBe(3)
        ->and($laravel->profile->commits()->topLevel()->count())->toBe(2)
        ->and($devNull->profile->commits()->whereNotIn('user_id', $devNull->members()->select('users.id'))->exists())->toBeFalse()
        ->and($laravel->profile->commits()->whereNotIn('user_id', $laravel->members()->select('users.id'))->exists())->toBeFalse()
        ->and($following->where('author_id', $laravel->id))->toHaveCount(2);
});

it('reuses existing GitHub organizations', function (): void {
    $laravel = Organization::factory()->create(['handle' => 'laravel']);

    $this->seed(DatabaseSeeder::class);

    expect(Organization::query()->whereRelation('profile', 'handle', 'laravel')->sole()->id)->toBe($laravel->id)
        ->and($laravel->profile->refresh()->name)->toBe('Laravel')
        ->and(resolve(OrganizationMentionsQuery::class)->builder($laravel, null)->count())->toBe(3);
});

it('reuses a GitHub organization renamed since it was seeded', function (): void {
    $laravel = Organization::factory()->create(['handle' => 'laravel-old', 'github_id' => '958072']);

    $this->seed(DatabaseSeeder::class);

    expect(Organization::query()->whereRelation('profile', 'github_id', '958072')->sole()->id)->toBe($laravel->id)
        ->and($laravel->profile->refresh()->handle)->toBe('laravel');
});

it('makes the local developer an admin of /dev/null when they are already a member', function (): void {
    $localDeveloper = User::factory()->create(['github_id' => 'local']);
    $devNull = Organization::factory()->create(['handle' => 'dev-null-sh']);
    $devNull->members()->attach($localDeveloper->id, ['role' => OrganizationRole::Member]);

    $this->seed(DatabaseSeeder::class);

    expect($devNull->roleOf($localDeveloper))->toBe(OrganizationRole::Admin);
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
    'requests off' => [false, 0, 2],
    'requests on' => [true, 1, 3],
]);
