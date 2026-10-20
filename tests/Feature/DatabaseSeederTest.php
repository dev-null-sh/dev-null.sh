<?php

declare(strict_types=1);

use App\Enums\InboxTab;
use App\Enums\NotificationType;
use App\Models\Notification;
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

it('can seed again without duplicating existing follow notifications', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();
    $notifications = $localDeveloper->notifications()->get();
    $users = User::query()->pluck('id');

    $this->seed(DatabaseSeeder::class);

    $newNotifications = $localDeveloper->notifications()->whereNotIn('id', $notifications->modelKeys())->get();

    expect(User::query()->where('github_id', 'local')->sole()->id)->toBe($localDeveloper->id)
        ->and($localDeveloper->following()->count())->toBe(6)
        ->and($localDeveloper->followers()->count())->toBe(10)
        ->and($localDeveloper->notifications()->count())->toBe(8)
        ->and($newNotifications)->toHaveCount(4)
        ->and($newNotifications->pluck('actor_id')->intersect($users))->toBeEmpty()
        ->and($newNotifications->pluck('type')->all())->toEqualCanonicalizing([
            NotificationType::Followed,
            NotificationType::Starred,
            NotificationType::Replied,
            NotificationType::Mentioned,
        ]);

    $notifications->each(fn (Notification $notification) => $this->assertModelExists($notification));
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
