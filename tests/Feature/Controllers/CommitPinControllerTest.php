<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('pins an own top-level commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('user.show', $user))
        ->put("/commits/{$commit->id}/pin")
        ->assertRedirectToRoute('user.show', $user);

    expect($user->fresh()->pinned_commit_id)->toBe($commit->id);
});

it('replaces a pin idempotently without changing identity or content', function (string $status): void {
    $user = User::factory()->create();
    $originalIdentity = $user->getAttributes();
    $first = Commit::factory()->for($user)->create();
    $replacement = Commit::factory()->for($user)->create(['status' => $status]);

    $this->actingAs($user)->put(route('commit-pin.update', $first))->assertRedirect();
    $this->put(route('commit-pin.update', $replacement), ['name' => 'Changed', 'body' => 'Changed'])->assertRedirect();
    $this->put(route('commit-pin.update', $replacement))->assertRedirect();

    expect($user->fresh()->pinned_commit_id)->toBe($replacement->id)
        ->and($user->fresh()->getAttributes())->toMatchArray(array_diff_key($originalIdentity, array_flip(['updated_at', 'pinned_commit_id'])))
        ->and($replacement->fresh()->body)->toBe($replacement->body)
        ->and(Commit::query()->count())->toBe(2);
})->with(['approved', 'pending', 'rejected']);

it('unpins idempotently and does not clear a replacement on a stale unpin', function (): void {
    $user = User::factory()->create();
    $first = Commit::factory()->for($user)->create();
    $replacement = Commit::factory()->for($user)->create();

    $this->actingAs($user)->put(route('commit-pin.update', $first))->assertRedirect();
    $this->put(route('commit-pin.update', $replacement))->assertRedirect();
    $this->delete(route('commit-pin.destroy', $first))->assertRedirect();

    expect($user->fresh()->pinned_commit_id)->toBe($replacement->id);

    $this->delete(route('commit-pin.destroy', $replacement))->assertRedirect();
    $this->delete(route('commit-pin.destroy', $replacement))->assertRedirect();

    expect($user->fresh()->pinned_commit_id)->toBeNull();
});

it('requires sign in to change a pin', function (string $method): void {
    $commit = Commit::factory()->create();

    $this->{$method}(route('commit-pin.update', $commit))->assertRedirectToRoute('home');

    expect($commit->user->fresh()->pinned_commit_id)->toBeNull();
})->with(['put', 'delete']);

it('forbids changing another author pin even for an admin', function (string $method): void {
    $commit = Commit::factory()->create();
    $viewer = User::factory()->create(['handle' => 'admin']);
    config(['app.admins' => ['admin']]);
    $commit->user->update(['pinned_commit_id' => $commit->id]);

    $this->actingAs($viewer)->{$method}(route('commit-pin.update', $commit))->assertForbidden();

    expect($commit->user->fresh()->pinned_commit_id)->toBe($commit->id)
        ->and($viewer->fresh()->pinned_commit_id)->toBeNull();
})->with(['put', 'delete']);

it('forbids pinning or unpinning replies', function (string $method): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($user)->for($parent, 'parent')->create();

    $this->actingAs($user)->{$method}(route('commit-pin.update', $reply))->assertForbidden();

    expect($user->fresh()->pinned_commit_id)->toBeNull();
})->with(['put', 'delete']);

it('cannot change a deleted or missing pin', function (string $method): void {
    $user = User::factory()->create();
    $deleted = Commit::factory()->for($user)->create();
    $deleted->delete();

    $this->actingAs($user)->{$method}(route('commit-pin.update', $deleted))->assertNotFound();
    $this->{$method}('/commits/missing/pin')->assertNotFound();

    expect($user->fresh()->pinned_commit_id)->toBeNull();
})->with(['put', 'delete']);
