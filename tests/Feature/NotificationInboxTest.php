<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;

it('groups nested replies and mentions and bumps an old conversation using its latest event', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->for($user)->create(['created_at' => now()->subMonth()]);
    $first = Commit::factory()->for($root, 'parent')->create();
    $nested = Commit::factory()->for($first, 'parent')->create();
    $old = Notification::factory()->for($user, 'recipient')->for($first, 'commit')->create([
        'type' => NotificationType::Replied, 'created_at' => now()->subMonth(), 'read_at' => now()->subWeek(),
    ]);
    $other = Notification::factory()->for($user, 'recipient')->replied()->create(['created_at' => now()->subHour()]);
    $latest = Notification::factory()->for($user, 'recipient')->for($nested, 'commit')->create([
        'type' => NotificationType::Mentioned, 'created_at' => now(),
    ]);

    $this->actingAs($user)->get(route('notification.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('notifications.data', 2)
        ->where('notifications.data.0.id', $latest->id)
        ->where('notifications.data.0.group_id', $root->id)
        ->where('notifications.data.0.event_count', 2)
        ->where('notifications.data.0.unread_count', 1)
        ->where('notifications.data.0.created_at', $latest->created_at->toJSON())
        ->where('notifications.data.1.id', $other->id));

    $this->getJson(route('notification-group.show', $latest))->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $latest->id)
        ->assertJsonPath('data.1.id', $old->id)->assertJsonMissingPath('data.0.commit.parent');
});

it('orders tied timestamps by the latest notification id', function (): void {
    $user = User::factory()->create();
    $first = Notification::factory()->for($user, 'recipient')->replied()->create();
    $second = Notification::factory()->for($user, 'recipient')->replied()->create();
    $expected = collect([$first->id, $second->id])->sortDesc()->values()->all();

    $this->actingAs($user)->get(route('notification.index'))->assertInertia(fn ($page) => $page
        ->where('notifications.data.0.id', $expected[0])->where('notifications.data.1.id', $expected[1]));
});

it('paginates complete groups rather than splitting a busy conversation', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->for($user)->create();
    for ($index = 0; $index < 24; $index++) {
        $reply = Commit::factory()->for($root, 'parent')->create();
        Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create(['type' => NotificationType::Replied]);
    }
    Notification::factory()->for($user, 'recipient')->count(21)->replied()->create(['created_at' => now()->subDay()]);

    $first = resolve(NotificationGroupsQuery::class)->get($user);
    expect($first->items())->toHaveCount(20)
        ->and($first->items()[0]->event_count)->toBe(24);
    $firstIds = collect($first->items())->pluck('group_id')->all();

    $members = $this->actingAs($user)->getJson(route('notification-group.show', $first->items()[0]))
        ->assertOk()->assertJsonCount(20, 'data');
    $this->getJson(route('notification-group.show', [$first->items()[0], 'cursor' => $members->json('next_cursor')]))
        ->assertOk()->assertJsonCount(4, 'data');

    $this->actingAs($user)->get(route('notification.index', ['cursor' => $first->nextCursor()->encode()]))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 2)
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
        ->has('notifications.data', 1)->where('notifications.data.0.group_id', $root->id)
        ->where('notifications.data.0.event_count', 2));
    $this->getJson(route('notification-group.show', $one))->assertOk()->assertDontSee('Hidden ancestor body');
});

it('filters event types and retains read members of unread groups', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    foreach ([null, now()->subDay()] as $readAt) {
        $reply = Commit::factory()->for($root, 'parent')->create();
        Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create(['type' => NotificationType::Replied, 'read_at' => $readAt]);
    }
    Notification::factory()->for($user, 'recipient')->mentioned()->read()->create();
    Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->get(route('notification.index', ['type' => 'replies', 'unread' => 1]))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1)
            ->where('notifications.data.0.event_count', 2)->where('notifications.data.0.unread_count', 1)
            ->where('filters.type', 'replies')->where('filters.unread', true));
    $this->get(route('notification.index', ['type' => 'mentions', 'unread' => 1]))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 0));
    $this->get(route('notification.index', ['type' => 'follows']))
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1)->where('notifications.data.0.type', 'followed'));
});

it('marks only a selected notification read and unread across reloads', function (): void {
    $user = User::factory()->create();
    $one = Notification::factory()->for($user, 'recipient')->create();
    $other = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->from(route('notification.index'))
        ->put(route('notification-read.update', $one), ['read' => true, 'group' => false, 'type' => 'all'])
        ->assertRedirectToRoute('notification.index');
    $readAt = $one->refresh()->read_at;
    expect($readAt)->not->toBeNull()->and($other->refresh()->read_at)->toBeNull();
    $this->travel(5)->minutes();
    $this->put(route('notification-read.update', $one), ['read' => true, 'group' => false, 'type' => 'all'])->assertRedirect();
    expect($one->refresh()->read_at->equalTo($readAt))->toBeTrue();
    $this->put(route('notification-read.update', $one), ['read' => false, 'group' => false, 'type' => 'all'])->assertRedirect();
    $this->get(route('notification.index'))->assertOk();
    expect($one->refresh()->read_at)->toBeNull();
});

it('marks a displayed group read without consuming newer hidden or unrelated activity', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $events = collect();
    foreach ([-30, -20, 0] as $minutes) {
        $reply = Commit::factory()->for($root, 'parent')->create();
        $events->push(Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create([
            'type' => NotificationType::Replied, 'created_at' => now()->addMinutes($minutes),
        ]));
    }
    $hiddenReply = Commit::factory()->for($root, 'parent')->create(['status' => CommitStatus::Pending]);
    $hidden = Notification::factory()->for($user, 'recipient')->for($hiddenReply, 'commit')->create(['type' => NotificationType::Replied]);
    $other = Notification::factory()->for($user, 'recipient')->replied()->create();

    $this->actingAs($user)->put(route('notification-read.update', $events[1]), ['read' => true, 'group' => true, 'type' => 'all'])->assertRedirect();
    expect($events[0]->refresh()->read_at)->not->toBeNull()
        ->and($events[1]->refresh()->read_at)->not->toBeNull()
        ->and($events[2]->refresh()->read_at)->toBeNull()
        ->and($hidden->refresh()->read_at)->toBeNull()
        ->and($other->refresh()->read_at)->toBeNull();

    $this->put(route('notification-read.update', $events[1]), ['read' => false, 'group' => true, 'type' => 'all'])->assertRedirect();
    expect($events[0]->refresh()->read_at)->toBeNull()->and($events[1]->refresh()->read_at)->toBeNull();
});

it('restricts filtered group mutations to the selected event type', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $reply = Commit::factory()->for($root, 'parent')->create();
    $mention = Notification::factory()->for($user, 'recipient')->for($root, 'commit')->create(['type' => NotificationType::Mentioned, 'created_at' => now()->subHour()]);
    $replied = Notification::factory()->for($user, 'recipient')->for($reply, 'commit')->create(['type' => NotificationType::Replied]);

    $this->actingAs($user)->put(route('notification-read.update', $replied), ['read' => true, 'group' => true, 'type' => 'replies'])->assertRedirect();
    expect($replied->refresh()->read_at)->not->toBeNull()->and($mention->refresh()->read_at)->toBeNull();
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

it('rejects other recipients and irrelevant notifications', function (): void {
    $user = User::factory()->create();
    $other = Notification::factory()->create();
    $hidden = Notification::factory()->for($user, 'recipient')->replied()->create();
    $hidden->commit->update(['status' => CommitStatus::Rejected]);

    foreach ([$other, $hidden] as $notification) {
        $this->actingAs($user)->getJson(route('notification-group.show', $notification))->assertNotFound();
        $this->put(route('notification-read.update', $notification), ['read' => true, 'group' => true, 'type' => 'all'])->assertNotFound();
        expect($notification->refresh()->read_at)->toBeNull();
    }
});

it('requires authentication for group reads and read mutations', function (): void {
    $notification = Notification::factory()->create();
    $this->get(route('notification-group.show', $notification))->assertRedirectToRoute('home');
    $this->put(route('notification-read.update', $notification), ['read' => true, 'group' => true, 'type' => 'all'])->assertRedirectToRoute('home');
});

it('validates read controls and filters', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)->put(route('notification-read.update', $notification), [])
        ->assertSessionHasErrors(['read' => 'The read field is required.', 'group' => 'The group field is required.', 'type' => 'The type field is required.']);
    $this->put(route('notification-read.update', $notification), ['read' => 'yes', 'group' => 'all', 'type' => 'secrets'])
        ->assertSessionHasErrors(['read' => 'The read field must be true or false.', 'group' => 'The group field must be true or false.', 'type' => 'The selected type is invalid.']);
    $this->getJson(route('notification.index', ['type' => 'secret', 'unread' => 'yes']))->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'unread']);
    $this->getJson(route('notification-group.show', [$notification, 'type' => 'secret']))->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->get(route('notification.index', ['type' => '']))->assertOk();
});
