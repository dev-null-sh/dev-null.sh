<?php

declare(strict_types=1);

use App\Actions\CreateCommitStar;
use App\Actions\DeleteUserFollow;
use App\Models\Commit;
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

    resolve(DeleteUserFollow::class)->handle($notification->actor, $user);

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

it('groups stars per commit, newest stargazer first', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $stargazers = User::factory()->count(4)->create();

    foreach ($stargazers as $index => $stargazer) {
        $this->travelTo(now()->subMinutes(4 - $index), fn () => resolve(CreateCommitStar::class)->handle($stargazer, $commit));
    }

    $response = $this->actingAs($user)->get(route('notification.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('notifications.data', 1)
        ->where('notifications.data.0.type', 'starred')
        ->where('notifications.data.0.actor.id', $stargazers[3]->id)
        ->where('notifications.data.0.commit.id', $commit->id)
        ->where('notifications.data.0.actors_count', 4)
        ->where('notifications.data.0.unread_count', 4)
        ->has('notifications.data.0.actors', 3)
        ->where('notifications.data.0.actors.0.id', $stargazers[3]->id)
        ->missing('notifications.data.0.actors.0.email')
        ->missing('notifications.data.0.position'));

    expect($user->notifications()->unread()->exists())->toBeFalse();
});

it('requires authentication', function (): void {
    $response = $this->get(route('notification.index'));

    $response->assertRedirectToRoute('home');
});
