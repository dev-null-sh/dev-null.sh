<?php

declare(strict_types=1);

use App\Enums\InboxTab;
use App\Models\Notification;
use App\Models\User;
use App\Queries\ConversationsQuery;
use App\Queries\NotificationGroupsQuery;
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
