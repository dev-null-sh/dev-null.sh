<?php

declare(strict_types=1);

use App\Actions\UnfollowUser;
use App\Models\Notification;
use App\Models\User;

it('lists the notifications of the user, newest first', function (): void {
    $user = User::factory()->create();
    $followed = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subMinute()]);
    $replied = Notification::factory()->for($user, 'recipient')->replied()->create();
    Notification::factory()->create();

    $response = $this->actingAs($user)->get(route('notification.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notification/index')
            ->has('notifications.data', 2)
            ->where('notifications.data.0.id', $replied->id)
            ->where('notifications.data.0.type', 'replied')
            ->where('notifications.data.0.actor.id', $replied->actor_id)
            ->where('notifications.data.0.commit.id', $replied->commit_id)
            ->missing('notifications.data.0.commit.parent')
            ->where('notifications.data.1.id', $followed->id)
            ->missing('notifications.data.0.key')
            ->missing('notifications.data.0.actor.email'));
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
