<?php

declare(strict_types=1);

use App\Actions\CreateStreakShieldNotifications;
use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Jobs\CheckStreakShields;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use App\Queries\UserStreakQuery;
use Illuminate\Support\Facades\Event;

it('notifies earning and offline shield use once, including the last-shield warning', function (): void {
    $user = User::factory()->create(['streak_shield_notifications_started_at' => now()->subDays(10)]);
    foreach (range(2, 8) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    Event::fake([UsersUpdated::class]);
    $action = resolve(CreateStreakShieldNotifications::class);
    $action->handle($user);
    $action->handle($user);

    $notifications = $user->notifications()->orderBy('key')->get();

    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('type')->all())->toBe([NotificationType::StreakShieldEarned, NotificationType::StreakShieldUsed])
        ->and($notifications->last()?->reason)->toContain('0/2 shields remaining', 'Your next missed day')
        ->and($notifications->last()?->actor_id)->toBe($user->id)
        ->and($notifications->last()?->commit_id)->toBeNull()
        ->and(resolve(NotificationGroupsQuery::class)->unreadCount($user))->toBe(2);
    Event::assertDispatchedTimes(UsersUpdated::class, 1);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Notifications);
});

it('does not send a backlog of historical events or notify from profile reads', function (): void {
    $user = User::factory()->create();
    foreach (range(3, 9) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    resolve(UserStreakQuery::class)->get($user, $user);
    resolve(CreateStreakShieldNotifications::class)->handle($user);

    expect(Notification::query()->count())->toBe(0);
});

it('checks inactive users and notifies a broken streak only once while skipping banned users', function (): void {
    $user = User::factory()->create(['streak_shield_notifications_started_at' => now()->subDays(3)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
    $banned = User::factory()->create(['banned_at' => now(), 'streak_shield_notifications_started_at' => now()->subDays(3)]);
    Commit::factory()->for($banned)->create(['created_at' => now()->subDays(2)]);
    dispatch_sync(new CheckStreakShields());
    dispatch_sync(new CheckStreakShields());
    expect($user->notifications()->sole()->type)->toBe(NotificationType::StreakEnded)
        ->and($banned->notifications()->count())->toBe(0);
});

it('notifies new earnings today without rewarding a full balance', function (): void {
    $user = User::factory()->create();
    foreach (range(0, 6) as $day) {
        Commit::factory()->for($user)->create(['created_at' => now()->subDays($day)]);
    }

    $action = resolve(CreateStreakShieldNotifications::class);
    $action->handle($user);

    expect($user->notifications()->sole()->reason)->toContain('1/2 shields available');

    $full = User::factory()->create();
    foreach (range(0, 20) as $day) {
        Commit::factory()->for($full)->create(['created_at' => now()->subDays($day)]);
    }

    $action->handle($full);
    expect($full->notifications()->count())->toBe(0);
});
