<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;

it('polls only the unread count outside the inbox without acknowledging activity', function (): void {
    $user = User::factory()->create();
    $notice = Notification::factory()->for($user, 'recipient')->create();
    $hidden = Notification::factory()->for($user, 'recipient')->replied()->create();
    $hidden->commit->update(['status' => CommitStatus::Pending]);
    Notification::factory()->create();

    $this->actingAs($user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'auth.unreadNotificationsCount',
    ])->get(route('log.show', 'trending'))->assertOk()
        ->assertJsonPath('props.auth.unreadNotificationsCount', 1)
        ->assertJsonMissingPath('props.auth.user')->assertJsonMissingPath('props.commits');

    expect($notice->refresh()->read_at)->toBeNull();
});

it('polls only the unread count in a hidden inbox without acknowledging activity', function (): void {
    $user = User::factory()->create();
    $notice = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'notification/index',
        'X-Inertia-Partial-Data' => 'auth.unreadNotificationsCount,auth.unreadMessagesCount',
    ])->get(route('notification.index'))->assertOk()
        ->assertJsonPath('props.auth.unreadNotificationsCount', 1)
        ->assertJsonMissingPath('props.notifications')->assertJsonMissingPath('props.readAt');

    expect($notice->refresh()->read_at)->toBeNull();
});

it('filters the menu count using the current users saved notification types', function (string $type, int $expected): void {
    $user = User::factory()->create();
    $thread = Commit::factory()->for($user)->create();
    $reply = Notification::factory()->for($user, 'recipient')->replied($thread)->create();
    Notification::factory()->for($user, 'recipient')->for($reply->commit, 'commit')->mentioned()->read()->create();
    Notification::factory()->for($user, 'recipient')->replied($thread)->create();
    Notification::factory()->for($user, 'recipient')->create();
    Notification::factory()->for($user, 'recipient')->starred()->create();

    $this->actingAs($user)->withCookie('notification_types_'.$user->id, $type)
        ->withCookie('notification_types_'.User::factory()->create()->id, 'none')
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
            'X-Inertia-Partial-Component' => 'log/show',
            'X-Inertia-Partial-Data' => 'auth.unreadNotificationsCount',
        ])->get(route('log.show', ['log' => 'trending', 'type' => 'none']))->assertOk()
        ->assertJsonPath('props.auth.unreadNotificationsCount', $expected);

    expect($reply->refresh()->read_at)->toBeNull();
})->with([
    'all types' => ['all', 4],
    'replies' => ['replies', 2],
    'multiple types' => ['replies,stars', 3],
    'only read matches in an unread group' => ['mentions', 0],
    'no matching type' => ['quotes', 0],
    'no selected types' => ['none', 0],
    'invalid saved filter' => ['invalid', 4],
    'partially invalid saved filter' => ['replies,invalid', 4],
]);

it('refreshes the entire loaded inbox window without losing older pages', function (): void {
    $user = User::factory()->create();
    $notices = collect();
    for ($index = 1; $index <= 62; $index++) {
        $notices->push(Notification::factory()->for($user, 'recipient')->replied()->create([
            'created_at' => now()->subMinutes($index),
        ]));
    }

    $new = Notification::factory()->for($user, 'recipient')->create();
    $through = $notices[39]->created_at->toJSON();
    $response = $this->actingAs($user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'notification/index',
        'X-Inertia-Partial-Data' => 'notifications,auth.unreadNotificationsCount',
        'X-Inertia-Reset' => 'notifications',
    ])->get(route('notification.index', ['through' => $through]))->assertOk()
        ->assertJsonCount(41, 'props.notifications.data')
        ->assertJsonPath('props.notifications.data.0.id', $new->id)
        ->assertJsonPath('props.notifications.data.40.id', $notices[39]->id)
        ->assertJsonPath('props.auth.unreadNotificationsCount', 0)
        ->assertJsonMissingPath('props.auth.user');

    expect($new->refresh()->read_at)->not->toBeNull();
    $this->get($response->json('props.notifications.next_page_url'))
        ->assertJsonCount(20, 'props.notifications.data')
        ->assertJsonPath('props.notifications.data.0.id', $notices[40]->id);
});

it('rejects an invalid inbox refresh boundary without reading notifications', function (): void {
    $user = User::factory()->create();
    $notice = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->getJson(route('notification.index', ['through' => 'invalid']))
        ->assertUnprocessable()->assertJsonValidationErrors('through');
    expect($notice->refresh()->read_at)->toBeNull();
});

it('polls fresh filtered groups and excludes activity that is no longer relevant', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->for($user)->create();
    $oldReply = Commit::factory()->for($root, 'parent')->create();
    $old = Notification::factory()->for($user, 'recipient')->for($oldReply, 'commit')->create([
        'type' => NotificationType::Replied, 'created_at' => now()->subMinutes(40),
    ]);
    $otherGroup = Notification::factory()->for($user, 'recipient')->replied()->create(['created_at' => now()->subMinutes(10)]);
    $this->actingAs($user)->get(route('notification.index', ['type' => 'replies']))->assertOk();

    $latestReply = Commit::factory()->for($root, 'parent')->create();
    $latest = Notification::factory()->for($user, 'recipient')->for($latestReply, 'commit')->create(['type' => NotificationType::Replied]);
    $follow = Notification::factory()->for($user, 'recipient')->create();
    $otherGroup->commit->update(['status' => CommitStatus::Pending]);
    $other = Notification::factory()->create();

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'notification/index',
        'X-Inertia-Partial-Data' => 'notifications,auth.unreadNotificationsCount',
        'X-Inertia-Reset' => 'notifications',
    ])->get(route('notification.index', ['type' => 'replies', 'through' => $old->created_at->toJSON()]))
        ->assertOk()->assertJsonCount(2, 'props.notifications.data')
        ->assertJsonPath('props.notifications.data.0.id', $latest->id)
        ->assertJsonPath('props.notifications.data.0.group_id', $latest->id)
        ->assertJsonPath('props.notifications.data.0.event_count', 1)
        ->assertJsonPath('props.notifications.data.1.id', $old->id)
        ->assertJsonPath('props.auth.unreadNotificationsCount', 0);

    expect($latest->refresh()->read_at)->not->toBeNull()
        ->and($follow->refresh()->read_at)->not->toBeNull()
        ->and($other->refresh()->read_at)->toBeNull();
});

it('keeps nested replies separate from mentions in the same conversation', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->for($user)->create(['created_at' => now()->subMonth()]);
    $first = Commit::factory()->for($root, 'parent')->create();
    $nested = Commit::factory()->for($first, 'parent')->create();
    $old = Notification::factory()->for($user, 'recipient')->for($first, 'commit')->create([
        'type' => NotificationType::Replied, 'created_at' => now()->subMinutes(50), 'read_at' => now()->subMinutes(40),
    ]);
    $other = Notification::factory()->for($user, 'recipient')->replied()->create(['created_at' => now()->subMinute()]);
    $latest = Notification::factory()->for($user, 'recipient')->for($nested, 'commit')->create([
        'type' => NotificationType::Mentioned, 'created_at' => now(),
    ]);

    $this->actingAs($user)->get(route('notification.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('notifications.data', 3)
        ->where('notifications.data.0.id', $latest->id)
        ->where('notifications.data.0.group_id', $latest->id)
        ->where('notifications.data.0.event_count', 1)
        ->where('notifications.data.0.created_at', $latest->created_at->toJSON())
        ->where('notifications.data.1.id', $other->id)
        ->where('notifications.data.2.id', $old->id));

    $this->getJson(route('notification-group.show', $latest))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $latest->id)
        ->assertJsonMissingPath('data.0.commit.parent');
});

it('orders tied timestamps by the latest notification id', function (): void {
    $user = User::factory()->create();
    $first = Notification::factory()->for($user, 'recipient')->replied()->create();
    $second = Notification::factory()->for($user, 'recipient')->replied()->create();
    $expected = collect([$first->id, $second->id])->sortDesc()->values()->all();

    $this->actingAs($user)->get(route('notification.index'))->assertInertia(fn ($page) => $page
        ->where('notifications.data.0.id', $expected[0])->where('notifications.data.1.id', $expected[1]));
});

it('groups follows across people within a rolling hour and bumps the group', function (): void {
    $this->travelTo(today()->setTime(12, 0));
    $user = User::factory()->create();
    $first = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subMinutes(100)]);
    Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subMinutes(50)]);
    $reply = Notification::factory()->for($user, 'recipient')->replied()->create(['created_at' => now()->subMinute()]);
    $latest = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->get(route('notification.index'))->assertInertia(fn ($page) => $page
        ->has('notifications.data', 2)->where('notifications.data.0.id', $latest->id)
        ->where('notifications.data.0.event_count', 3)->where('notifications.data.1.id', $reply->id));
    $this->getJson(route('notification-group.show', $latest))->assertOk()->assertJsonCount(3, 'data')
        ->assertJsonPath('data.2.id', $first->id);
});

it('splits follows after an interrupted gap longer than an hour regardless of filters', function (int $seconds, int $groups): void {
    $this->travelTo(today()->setTime(12, 0));
    $user = User::factory()->create();
    $old = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subSeconds($seconds)]);
    Notification::factory()->for($user, 'recipient')->replied()->create(['created_at' => now()->subSecond()]);
    $latest = Notification::factory()->for($user, 'recipient')->create();

    foreach (['all', 'follows'] as $type) {
        $this->actingAs($user)->get(route('notification.index', ['type' => $type]))->assertInertia(fn ($page) => $page
            ->has('notifications.data', $groups + ($type === 'all' ? 1 : 0))
            ->where('notifications.data.0.id', $latest->id)
            ->where('notifications.data.0.event_count', $groups === 1 ? 2 : 1));
    }

    $this->getJson(route('notification-group.show', [$latest, 'type' => 'follows']))
        ->assertOk()->assertJsonCount($groups === 1 ? 2 : 1, 'data');
})->with(['exactly one hour' => [3600, 1], 'one second too long' => [3601, 2], 'two days' => [172800, 2]]);

it('extends the newest follow group after hours without intervening relevant activity', function (): void {
    $this->travelTo(today()->setTime(12, 0));
    $user = User::factory()->create();
    $old = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subHours(6)]);
    Notification::factory()->replied()->create(['created_at' => now()->subHour()]);
    $hidden = Notification::factory()->for($user, 'recipient')->replied()->create(['created_at' => now()->subMinute()]);
    $hidden->commit->delete();

    $latest = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->get(route('notification.index'))->assertInertia(fn ($page) => $page
        ->has('notifications.data', 1)->where('notifications.data.0.id', $latest->id)
        ->where('notifications.data.0.event_count', 2)->where('notifications.data.0.group_id', $old->id));
});

it('never groups follows across days, even when they keep coming', function (): void {
    $this->travelTo(today()->setTime(0, 30));
    $user = User::factory()->create();
    $old = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subMinutes(50)]);
    $yesterday = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subMinutes(40)]);
    $latest = Notification::factory()->for($user, 'recipient')->create();

    expect(resolve(NotificationGroupsQuery::class)->unreadCount($user))->toBe(2);

    foreach (['all', 'follows'] as $type) {
        $this->actingAs($user)->get(route('notification.index', ['type' => $type]))->assertInertia(fn ($page) => $page
            ->has('notifications.data', 2)
            ->where('notifications.data.0.id', $latest->id)->where('notifications.data.0.event_count', 1)
            ->where('notifications.data.1.id', $yesterday->id)->where('notifications.data.1.group_id', $old->id)
            ->where('notifications.data.1.event_count', 2));
    }
});

it('keeps a new mention separate from an older reply regardless of intervening activity', function (bool $interrupted): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($root, 'parent')->create();
    $nested = Commit::factory()->for($parent, 'parent')->create();
    $old = Notification::factory()->for($user, 'recipient')->for($parent, 'commit')->create([
        'type' => NotificationType::Replied, 'created_at' => now()->subDays(2),
    ]);
    if ($interrupted) {
        Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subMinute()]);
    }

    $latest = Notification::factory()->for($user, 'recipient')->for($nested, 'commit')->create([
        'type' => NotificationType::Mentioned,
    ]);

    $this->actingAs($user)->get(route('notification.index'))->assertInertia(fn ($page) => $page
        ->has('notifications.data', $interrupted ? 3 : 2)->where('notifications.data.0.id', $latest->id)
        ->where('notifications.data.0.event_count', 1)
        ->where('notifications.data.0.group_id', $latest->id));
    $this->get(route('notification.index', ['type' => 'replies']))->assertInertia(fn ($page) => $page
        ->has('notifications.data', 1)->where('notifications.data.0.group_id', $old->id));
    $this->getJson(route('notification-group.show', $latest))->assertOk()->assertJsonCount(1, 'data');
})->with(['uninterrupted' => false, 'interrupted' => true]);

it('paginates every reply in a busy conversation separately', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->for($user)->create();
    for ($index = 0; $index < 24; $index++) {
        $reply = Commit::factory()->for($root, 'parent')->create();
        Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create(['type' => NotificationType::Replied]);
    }

    Notification::factory()->for($user, 'recipient')->count(21)->replied()->create(['created_at' => now()->subDay()]);

    $first = resolve(NotificationGroupsQuery::class)->paginate($user);
    expect($first->items())->toHaveCount(20)
        ->and($first->items()[0]->event_count)->toBe(1);
    $firstIds = collect($first->items())->pluck('group_id')->all();

    $this->actingAs($user)->getJson(route('notification-group.show', $first->items()[0]))
        ->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($user)->get(route('notification.index', ['cursor' => $first->nextCursor()->encode()]))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 20)
            ->where('notifications.data', fn ($items): bool => collect($items)->pluck('group_id')->intersect($firstIds)->isEmpty()));
});

it('excludes hidden events and other recipients before counts previews and ordering', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->for($user)->create();
    $reply = Commit::factory()->for($root, 'parent')->create();
    $visible = Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create([
        'type' => NotificationType::Replied, 'created_at' => now()->subDay(),
    ]);
    $other = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subHour()]);
    foreach ([CommitStatus::Pending, CommitStatus::Rejected] as $status) {
        $hidden = Commit::factory()->for($root, 'parent')->create(['status' => $status]);
        Notification::factory()->for($user, 'recipient')->for($hidden, 'commit')->create(['type' => NotificationType::Replied]);
    }

    Notification::factory()->for($reply, 'commit')->create(['type' => NotificationType::Mentioned]);
    $deleted = Commit::factory()->for($root, 'parent')->create();
    Notification::factory()->for($user, 'recipient')->for($deleted, 'commit')->create(['type' => NotificationType::Replied]);
    $deleted->delete();

    $this->actingAs($user)->get(route('notification.index'))->assertInertia(fn ($page) => $page
        ->has('notifications.data', 2)->where('notifications.data.0.id', $other->id)
        ->where('notifications.data.1.id', $visible->id)->where('notifications.data.1.event_count', 1));
});

it('uses deleted ancestors only for group identity', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create(['body' => 'Hidden ancestor body']);
    $parent = Commit::factory()->for($root, 'parent')->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $one = Notification::factory()->for($user, 'recipient')->for($parent, 'commit')->create(['type' => NotificationType::Mentioned]);
    Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create(['type' => NotificationType::Mentioned]);
    $root->delete();

    $this->actingAs($user)->get(route('notification.index'))->assertInertia(fn ($page) => $page
        ->has('notifications.data', 1)->where('notifications.data.0.group_id', $one->id)
        ->where('notifications.data.0.event_count', 2));
    $this->getJson(route('notification-group.show', $one))->assertOk()->assertDontSee('Hidden ancestor body');
});

it('filters event types while retaining all members of each group', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    foreach ([null, now()->subDay()] as $readAt) {
        $reply = Commit::factory()->for($root, 'parent')->create();
        Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create(['type' => NotificationType::Replied, 'read_at' => $readAt]);
    }

    Notification::factory()->for($user, 'recipient')->mentioned()->read()->create();
    Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->get(route('notification.index', ['type' => 'replies']))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 2)
            ->where('notifications.data.0.event_count', 1)->where('filters.type', 'replies'));
    $this->get(route('notification.index', ['type' => 'mentions']))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1));
    $this->get(route('notification.index', ['type' => 'follows']))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1)->where('notifications.data.0.type', 'followed'));
});

it('preserves the first read timestamp when reopening the inbox', function (): void {
    $user = User::factory()->create();
    $one = Notification::factory()->for($user, 'recipient')->create();
    $this->actingAs($user)->get(route('notification.index'))->assertOk();
    $readAt = $one->refresh()->read_at;
    expect($readAt)->not->toBeNull();
    $this->travel(5)->minutes();
    $this->get(route('notification.index'))->assertOk();
    expect($one->refresh()->read_at->equalTo($readAt))->toBeTrue();
});

it('marks all relevant activity read beyond the first page and selected type', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $events = collect();
    for ($index = 0; $index < 24; $index++) {
        $reply = Commit::factory()->for($root, 'parent')->create();
        $events->push(Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create([
            'type' => NotificationType::Replied,
        ]));
    }

    $hiddenReply = Commit::factory()->for($root, 'parent')->create(['status' => CommitStatus::Pending]);
    $hidden = Notification::factory()->for($user, 'recipient')->for($hiddenReply, 'commit')->create(['type' => NotificationType::Replied]);
    Notification::factory()->for($user, 'recipient')->count(21)->replied()->create();
    $follow = Notification::factory()->for($user, 'recipient')->create();
    $other = Notification::factory()->replied()->create();

    $this->actingAs($user)->get(route('notification.index', ['type' => 'replies']))
        ->assertOk()->assertInertia(fn ($page) => $page->has('notifications.data', 20)
        ->where('auth.unreadNotificationsCount', 0));
    expect($user->notifications()->relevant()->unread()->count())->toBe(0)
        ->and($follow->refresh()->read_at)->not->toBeNull()
        ->and($events->last()->refresh()->read_at)->not->toBeNull()
        ->and($hidden->refresh()->read_at)->toBeNull()
        ->and($other->refresh()->read_at)->toBeNull();
});

it('leaves later arrivals unread until the inbox is visited again', function (): void {
    $user = User::factory()->create();
    Notification::factory()->for($user, 'recipient')->create();
    $this->actingAs($user)->get(route('notification.index'))->assertOk();
    $this->travel(5)->minutes();
    $latest = Notification::factory()->for($user, 'recipient')->mentioned()->create();

    $this->get(route('log.show', 'trending'))->assertInertia(fn ($page) => $page
        ->where('auth.unreadNotificationsCount', 1));
    expect($latest->refresh()->read_at)->toBeNull();
    $this->get(route('notification.index', ['type' => 'follows']))->assertInertia(fn ($page) => $page
        ->where('auth.unreadNotificationsCount', 0));
    expect($latest->refresh()->read_at)->not->toBeNull();
});

it('keeps moderation decisions independent from conversation groups', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create(['status' => CommitStatus::Rejected]);
    Notification::factory()->for($user, 'recipient')->for($commit, 'commit')->create([
        'type' => NotificationType::Rejected, 'reason' => 'Please remove the access token.',
    ]);
    Notification::factory()->for($user, 'recipient')->replied()->create();

    $this->actingAs($user)->get(route('notification.index', ['type' => 'moderation']))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'rejected')->where('notifications.data.0.event_count', 1)
            ->where('notifications.data.0.reason', 'Please remove the access token.'));
});

it('keeps lifetime star groups separate from conversations and supports filtering and expansion', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $old = Notification::factory()->for($user, 'recipient')->starred($commit)->create([
        'created_at' => now()->subDays(2),
    ]);
    $reply = Notification::factory()->for($user, 'recipient')->replied($commit)->create([
        'created_at' => now()->subMinute(),
    ]);
    $latest = Notification::factory()->for($user, 'recipient')->starred($commit)->create();

    $this->actingAs($user)->get(route('notification.index'))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 2)
            ->where('notifications.data.0.id', $latest->id)
            ->where('notifications.data.0.group_id', $old->id)
            ->where('notifications.data.0.event_count', 2)
            ->where('notifications.data.0.actors_count', 2)
            ->where('notifications.data.1.id', $reply->id));

    $this->get(route('notification.index', ['type' => 'stars']))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('filters.type', 'stars')
            ->where('notifications.data.0.group_id', $old->id)
            ->where('auth.unreadNotificationsCount', 0));

    $this->getJson(route('notification-group.show', [$latest, 'type' => 'stars']))->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $latest->id)
        ->assertJsonPath('data.1.id', $old->id);
});

it('rejects other recipients and irrelevant notifications', function (): void {
    $user = User::factory()->create();
    $other = Notification::factory()->create();
    $hidden = Notification::factory()->for($user, 'recipient')->replied()->create();
    $hidden->commit->update(['status' => CommitStatus::Rejected]);

    foreach ([$other, $hidden] as $notification) {
        $this->actingAs($user)->getJson(route('notification-group.show', $notification))->assertNotFound();
        expect($notification->refresh()->read_at)->toBeNull();
    }
});

it('requires authentication for group history', function (): void {
    $notification = Notification::factory()->create();
    $this->get(route('notification-group.show', $notification))->assertRedirectToRoute('home');
});

it('validates notification type filters', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->getJson(route('notification.index', ['type' => 'secret']))->assertUnprocessable()
        ->assertJsonValidationErrors('type');
    expect($notification->refresh()->read_at)->toBeNull();
    $this->getJson(route('notification-group.show', [$notification, 'type' => 'secret']))->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->get(route('notification.index', ['type' => '']))->assertOk();
});

it('includes quotes in the inbox and filters their history without reading hidden quotes', function (): void {
    $user = User::factory()->create();
    $quote = Notification::factory()->for($user, 'recipient')->quoted()->create();
    $hidden = Notification::factory()->for($user, 'recipient')->quoted()->create();
    $hidden->commit->update(['status' => CommitStatus::Pending]);
    Notification::factory()->for($user, 'recipient')->replied()->create();

    $this->actingAs($user)->get(route('notification.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 2));
    $this->get(route('notification.index', ['type' => 'quotes']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1)
            ->where('filters.type', 'quotes')
            ->where('notifications.data.0.id', $quote->id)
            ->where('notifications.data.0.type', 'quoted')
            ->where('notifications.data.0.commit.id', $quote->commit_id)
            ->where('auth.unreadNotificationsCount', 0));
    $this->getJson(route('notification-group.show', [$quote, 'type' => 'quotes']))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $quote->id);
    $this->getJson(route('notification-group.show', $hidden))->assertNotFound();

    expect($quote->refresh()->read_at)->not->toBeNull()
        ->and($hidden->refresh()->read_at)->toBeNull();
});

it('shows quotes with the recipients commit they quote and never loads commits quoted by others', function (): void {
    $user = User::factory()->create();
    $quote = Notification::factory()->for($user, 'recipient')->quoted()->create();
    $deleted = Notification::factory()->for($user, 'recipient')->quoted()->create(['created_at' => now()->subMinute()]);
    $deleted->commit->quotedCommit->delete();

    $reply = Notification::factory()->for($user, 'recipient')->replied()->create(['created_at' => now()->subMinutes(2)]);
    $reply->commit->update(['quoted_commit_id' => Commit::factory()->pending()->create()->id]);

    $this->actingAs($user)->get(route('notification.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 3)
            ->where('notifications.data.0.commit.quoted_commit.id', $quote->commit->quoted_commit_id)
            ->where('notifications.data.0.commit.quoted_commit.body', $quote->commit->quotedCommit->body)
            ->where('notifications.data.1.commit.quoted_commit.id', $deleted->commit->quoted_commit_id)
            ->whereNot('notifications.data.1.commit.quoted_commit.deleted_at', null)
            ->missing('notifications.data.1.commit.quoted_commit.body')
            ->where('notifications.data.2.commit.quoted_commit', null));
});

it('excludes silenced actors from groups, history and read acknowledgements', function (string $relation, bool $recipientActs): void {
    $user = User::factory()->create();
    $root = Commit::factory()->for($user)->create();
    $visible = Notification::factory()->for($user, 'recipient')->replied($root)->create();
    $hidden = Notification::factory()->for($user, 'recipient')->replied($root)->create();
    [$actor, $subject] = $recipientActs ? [$user, $hidden->actor] : [$hidden->actor, $user];
    $actor->{$relation}()->attach($subject);

    expect(resolve(NotificationGroupsQuery::class)->unreadCount($user))->toBe(1);

    $this->actingAs($user)->get(route('notification.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1)
            ->where('notifications.data.0.id', $visible->id)
            ->where('notifications.data.0.event_count', 1)
            ->where('notifications.data.0.actors_count', 1)
            ->where('auth.unreadNotificationsCount', 0));
    $this->getJson(route('notification-group.show', $visible))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
    $this->getJson(route('notification-group.show', $hidden))->assertNotFound();

    expect($visible->refresh()->read_at)->not->toBeNull()
        ->and($hidden->refresh()->read_at)->toBeNull();
})->with([
    'recipient muted the actor' => ['muting', true],
    'recipient blocked the actor' => ['blocking', true],
    'actor blocked the recipient' => ['blocking', false],
]);

it('keeps surviving replies separate through an authorless ancestor without exposing its content', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($root, 'parent')->create(['body' => 'Deleted private ancestor']);
    $first = Notification::factory()->for($user, 'recipient')->replied($parent)->create();
    $latest = Notification::factory()->for($user, 'recipient')->replied($parent)->create();

    resolve(DeleteUser::class)->handle($parent->user);

    expect($parent->withTrashed()->findOrFail($parent->id)->user_id)->toBeNull();

    $this->actingAs($user)->get(route('notification.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 2)
            ->where('notifications.data.0.id', $latest->id)
            ->where('notifications.data.0.group_id', $latest->id)
            ->where('notifications.data.0.event_count', 1)
            ->where('notifications.data.1.id', $first->id));
    $this->getJson(route('notification-group.show', $latest))->assertOk()
        ->assertJsonCount(1, 'data')->assertDontSee('Deleted private ancestor');
});

it('combines category filters and applies them to expanded groups', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $reply = Commit::factory()->for($root, 'parent')->create();
    $mention = Commit::factory()->for($root, 'parent')->create();
    $notification = Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create(['type' => NotificationType::Replied]);
    Notification::factory()->for($user, 'recipient')->for($mention, 'commit')->create(['type' => NotificationType::Mentioned]);
    Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->get(route('notification.index', ['type' => 'replies,mentions']))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 2)
            ->where('notifications.data.0.event_count', 1)->where('filters.type', 'replies,mentions'));
    $this->getJson(route('notification-group.show', [$notification, 'type' => 'replies,mentions']))
        ->assertOk()->assertJsonCount(1, 'data');
});

it('remembers selected categories for the current account including no categories', function (): void {
    $user = User::factory()->create();
    Notification::factory()->for($user, 'recipient')->create();
    $cookie = 'notification_types_'.$user->id;

    $this->actingAs($user)->get(route('notification.index', ['type' => 'none']))
        ->assertCookie($cookie, 'none')
        ->assertInertia(fn ($page) => $page->has('notifications.data', 0)->where('filters.type', 'none'));
    $this->withCookie($cookie, 'none')->get(route('notification.index'))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 0)->where('filters.type', 'none'));
    $this->get(route('notification.index', ['type' => 'all']))
        ->assertCookie($cookie, 'all')
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1)->where('filters.type', 'all'));
    $this->actingAs(User::factory()->create())->get(route('notification.index'))
        ->assertInertia(fn ($page) => $page->where('filters.type', 'all'));
});

it('rejects invalid combinations of categories', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route('notification.index', ['type' => 'replies,secret']))
        ->assertUnprocessable()->assertJsonValidationErrors('type');
});
