<?php

declare(strict_types=1);

use App\Actions\UnfollowUser;
use App\Models\Notification;
use App\Models\User;

it('lists the notifications of the user, newest first', function (): void {
    $user = User::factory()->create();
    $followed = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subMinutes(2)]);
    $replied = Notification::factory()->for($user, 'recipient')->replied()->create(['created_at' => now()->subMinute()]);
    $mentioned = Notification::factory()->for($user, 'recipient')->mentioned()->create();
    Notification::factory()->create();

    $response = $this->actingAs($user)->get(route('notification.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notification/index')
            ->has('notifications.data', 3)
            ->where('notifications.data.0.id', $mentioned->id)
            ->where('notifications.data.0.type', 'mentioned')
            ->where('notifications.data.0.actor.id', $mentioned->actor_id)
            ->where('notifications.data.0.commit.id', $mentioned->commit_id)
            ->where('notifications.data.1.id', $replied->id)
            ->where('notifications.data.1.type', 'replied')
            ->where('notifications.data.1.actor.id', $replied->actor_id)
            ->where('notifications.data.1.commit.id', $replied->commit_id)
            ->missing('notifications.data.1.commit.parent')
            ->where('notifications.data.2.id', $followed->id)
            ->missing('notifications.data.0.key')
            ->missing('notifications.data.0.actor.email'));
});

it('lists mentions of the user', function (): void {
    $user = User::factory()->create();
    $mentioned = Notification::factory()->for($user, 'recipient')->mentioned()->create();

    $response = $this->actingAs($user)->get(route('notification.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'mentioned')
            ->where('notifications.data.0.actor.id', $mentioned->actor_id)
            ->where('notifications.data.0.commit.id', $mentioned->commit_id));
});

it('hides notifications that no longer hold', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->for($user, 'recipient')->create();

    resolve(UnfollowUser::class)->handle($notification->actor, $user);

    $response = $this->actingAs($user)->get(route('notification.index'));

    $response->assertInertia(fn ($page) => $page->has('notifications.data', 0));
});

it('shows notifications as unread, then marks them as read', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->for($user, 'recipient')->create();

    $response = $this->actingAs($user)->get(route('notification.index'));

    $response->assertInertia(fn ($page) => $page->where('notifications.data.0.read_at', null));

    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('requires authentication', function (): void {
    $response = $this->get(route('notification.index'));

    $response->assertRedirectToRoute('home');
});
