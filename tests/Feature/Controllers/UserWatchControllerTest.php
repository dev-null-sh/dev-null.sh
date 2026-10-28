<?php

declare(strict_types=1);

use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;

it('may watch someone they follow', function (): void {
    $user = User::factory()->create();
    $watched = User::factory()->create(['handle' => 'octocat']);
    $user->following()->attach($watched);

    $response = $this->actingAs($user)
        ->fromRoute('user.show', $watched)
        ->post(route('user-watch.store', $watched));

    $response->assertRedirectToRoute('user.show', $watched)
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => "You'll be notified when @octocat commits.",
        ]);

    expect($user->isWatching($watched))->toBeTrue();
});

it('may not watch someone they do not follow', function (): void {
    $user = User::factory()->create();
    $watched = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('user-watch.store', $watched));

    $response->assertForbidden();

    expect($user->isWatching($watched))->toBeFalse();
});

it('may stop watching someone', function (): void {
    $user = User::factory()->create();
    $watched = User::factory()->create(['handle' => 'octocat']);
    $user->following()->attach($watched, ['watching' => true]);

    $response = $this->actingAs($user)
        ->fromRoute('user.show', $watched)
        ->delete(route('user-watch.destroy', $watched));

    $response->assertRedirectToRoute('user.show', $watched)
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => "You won't be notified when @octocat commits.",
        ]);

    expect($user->isWatching($watched))->toBeFalse()
        ->and($user->isFollowing($watched))->toBeTrue();
});

it('stops watching when unfollowing', function (): void {
    $user = User::factory()->create();
    $watched = User::factory()->create();
    $user->following()->attach($watched, ['watching' => true]);

    $this->actingAs($user)->delete(route('user-follow.destroy', $watched));
    $this->actingAs($user)->post(route('user-follow.store', $watched));

    expect($user->isWatching($watched))->toBeFalse();
});

it('requires authentication to watch', function (): void {
    $user = User::factory()->create();

    $this->post(route('user-watch.store', $user))->assertRedirectToRoute('home');
});

it('tells the profile whether the viewer watches it', function (): void {
    $user = User::factory()->create();
    $watched = User::factory()->create();
    $user->following()->attach($watched, ['watching' => true]);

    $this->actingAs($user)
        ->get(route('user.show', $watched))
        ->assertInertia(fn ($page) => $page->where('isWatching', true));
});

it('notifies watchers once the commit of the author is approved', function (): void {
    $author = User::factory()->create();
    $watcher = User::factory()->create();
    $watcher->following()->attach($author, ['watching' => true]);
    $commit = Commit::factory()->for($author)->pending()->create();

    resolve(UpdateCommitStatuses::class)->handle([$commit->id], CommitStatus::Approved);

    $this->actingAs($watcher)
        ->get(route('notification.index', ['type' => 'commits']))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.data.0.type', NotificationType::Committed->value)
            ->where('notifications.data.0.commit_id', $commit->id));
});
