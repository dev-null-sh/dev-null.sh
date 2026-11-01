<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;

it('updates the notifications read through the newest one the inbox showed', function (): void {
    $user = User::factory()->create();
    $shown = Notification::factory()->for($user, 'recipient')->create(['created_at' => now()->subMinute()]);
    $arrived = Notification::factory()->for($user, 'recipient')->create();
    $other = Notification::factory()->create(['created_at' => now()->subMinute()]);

    $this->actingAs($user)
        ->putJson(route('notification-read-at.update'), ['through' => $shown->created_at->toJSON()])
        ->assertNoContent();

    expect($shown->refresh()->read_at->toJSON())->toBe(now()->startOfSecond()->toJSON())
        ->and($arrived->refresh()->read_at)->toBeNull()
        ->and($other->refresh()->read_at)->toBeNull();
});

it('clears the badge once the notifications are read', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)
        ->putJson(route('notification-read-at.update'), ['through' => $notification->created_at->toJSON()])
        ->assertNoContent();

    $this->actingAs($user)
        ->get(route('notification.index'))
        ->assertInertia(fn ($page) => $page->where('auth.unreadNotificationsCount', 0));
});

it('refuses to update the notifications read without a moment', function (mixed $through): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->for($user, 'recipient')->create();

    $this->actingAs($user)
        ->putJson(route('notification-read-at.update'), ['through' => $through])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('through');

    expect($notification->refresh()->read_at)->toBeNull();
})->with([
    'missing' => [null],
    'not a date' => ['yesterday-ish'],
    'a list' => [['2026-11-01']],
]);

it('requires authentication to update the notifications read', function (): void {
    $notification = Notification::factory()->create();

    $this->putJson(route('notification-read-at.update'), ['through' => now()->toJSON()])
        ->assertUnauthorized();

    expect($notification->refresh()->read_at)->toBeNull();
});
