<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Notifications\NewCommitFromFollowing;
use Inertia\Testing\AssertableInertia as Assert;

it('shows notifications without marking them as read', function (): void {
    $user = User::factory()->create();
    $user->notify(new NewCommitFromFollowing(Commit::factory()->create()));

    $response = $this->actingAs($user)->get(route('notification.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('notifications/index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.read_at', null)
            ->where('auth.unreadNotificationsCount', 1));
});

it('marks a notification as read when opened', function (): void {
    $author = User::factory()->create(['username' => 'taylor']);
    $user = User::factory()->create();
    $user->notify(new NewCommitFromFollowing(Commit::factory()->for($author)->create()));

    $response = $this->actingAs($user)
        ->get(route('notification.show', $user->notifications()->sole()));

    $response->assertRedirectToRoute('user.show', $author);

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('may not open notifications of others', function (): void {
    $other = User::factory()->create();
    $other->notify(new NewCommitFromFollowing(Commit::factory()->create()));

    $response = $this->actingAs(User::factory()->create())
        ->get(route('notification.show', $other->notifications()->sole()));

    $response->assertNotFound();

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('requires authentication', function (): void {
    $response = $this->get(route('notification.index'));

    $response->assertRedirectToRoute('home');
});

it('may clear a notification', function (): void {
    $user = User::factory()->create();
    $user->notify(new NewCommitFromFollowing(Commit::factory()->create()));
    $user->notify(new NewCommitFromFollowing(Commit::factory()->create()));

    $response = $this->actingAs($user)
        ->fromRoute('notification.index')
        ->delete(route('notification.destroy', $user->notifications()->first()));

    $response->assertRedirectToRoute('notification.index');

    expect($user->notifications()->count())->toBe(1);
});

it('may not clear notifications of others', function (): void {
    $other = User::factory()->create();
    $other->notify(new NewCommitFromFollowing(Commit::factory()->create()));

    $response = $this->actingAs(User::factory()->create())
        ->delete(route('notification.destroy', $other->notifications()->first()));

    $response->assertNotFound();

    expect($other->notifications()->count())->toBe(1);
});

it('may clear all notifications', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->notify(new NewCommitFromFollowing(Commit::factory()->create()));
    $user->notify(new NewCommitFromFollowing(Commit::factory()->create()));

    $other->notify(new NewCommitFromFollowing(Commit::factory()->create()));

    $response = $this->actingAs($user)
        ->fromRoute('notification.index')
        ->delete(route('notification-clear.destroy'));

    $response->assertRedirectToRoute('notification.index');

    expect($user->notifications()->count())->toBe(0)
        ->and($other->notifications()->count())->toBe(1);
});

it('may mark all notifications as read', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->notify(new NewCommitFromFollowing(Commit::factory()->create()));
    $user->notify(new NewCommitFromFollowing(Commit::factory()->create()));

    $other->notify(new NewCommitFromFollowing(Commit::factory()->create()));

    $response = $this->actingAs($user)
        ->fromRoute('notification.index')
        ->post(route('notification-read.store'));

    $response->assertRedirectToRoute('notification.index');

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});
