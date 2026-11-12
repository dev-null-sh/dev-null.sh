<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('pins an own top-level commit and answers with it pinned', function (string $status): void {
    $commit = Commit::factory()->for($this->user)->create(['status' => $status]);

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-commit-pin.update', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.is_pinned', true);

    expect($this->user->fresh()->pinned_commit_id)->toBe($commit->id);
})->with(['approved', 'pending', 'rejected']);

it('replaces the commit pinned before', function (): void {
    $first = Commit::factory()->for($this->user)->create();
    $replacement = Commit::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-commit-pin.update', $first))->assertOk();
    signedApiRequest($this->keyPair, 'PUT', route('api-commit-pin.update', $replacement))
        ->assertOk()
        ->assertJsonPath('data.is_pinned', true);

    expect($this->user->fresh()->pinned_commit_id)->toBe($replacement->id);
});

it('unpins idempotently and answers with it unpinned', function (): void {
    $commit = Commit::factory()->for($this->user)->create();
    $this->user->update(['pinned_commit_id' => $commit->id]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-commit-pin.destroy', $commit))
        ->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.is_pinned', false);
    signedApiRequest($this->keyPair, 'DELETE', route('api-commit-pin.destroy', $commit))->assertOk();

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
});

it('does not clear a replacement on a stale unpin', function (): void {
    $first = Commit::factory()->for($this->user)->create();
    $replacement = Commit::factory()->for($this->user)->create();
    $this->user->update(['pinned_commit_id' => $replacement->id]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-commit-pin.destroy', $first))
        ->assertOk()
        ->assertJsonPath('data.is_pinned', false);

    expect($this->user->fresh()->pinned_commit_id)->toBe($replacement->id);
});

it('forbids changing the pin of another author, even for an admin', function (string $method, string $route): void {
    config(['app.admins' => ['admin']]);
    $admin = User::factory()->create(['handle' => 'admin']);
    $commit = Commit::factory()->create();
    $commit->user->update(['pinned_commit_id' => $commit->id]);

    signedApiRequest(apiKeyPairFor($admin), $method, route($route, $commit))->assertForbidden();

    expect($commit->user->fresh()->pinned_commit_id)->toBe($commit->id)
        ->and($admin->fresh()->pinned_commit_id)->toBeNull();
})->with([
    'pin' => ['PUT', 'api-commit-pin.update'],
    'unpin' => ['DELETE', 'api-commit-pin.destroy'],
]);

it('forbids pinning or unpinning a reply', function (string $method, string $route): void {
    $reply = Commit::factory()->for($this->user)->for(Commit::factory(), 'parent')->create();

    signedApiRequest($this->keyPair, $method, route($route, $reply))->assertForbidden();

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
})->with([
    'pin' => ['PUT', 'api-commit-pin.update'],
    'unpin' => ['DELETE', 'api-commit-pin.destroy'],
]);

it('hides the commits the user may not see', function (string $method, string $route, Closure $commit): void {
    $commit = $commit($this->user);

    signedApiRequest($this->keyPair, $method, route($route, $commit))->assertNotFound();

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
})->with([
    'pin' => ['PUT', 'api-commit-pin.update'],
    'unpin' => ['DELETE', 'api-commit-pin.destroy'],
])->with([
    'pending' => fn (User $user): Commit => Commit::factory()->pending()->create(),
    'across a block' => function (User $user): Commit {
        $commit = Commit::factory()->create();
        $user->blocking()->attach($commit->user);

        return $commit;
    },
]);

it('cannot change the pin of a deleted commit', function (string $method, string $route): void {
    $commit = Commit::factory()->for($this->user)->create();
    $commit->delete();

    signedApiRequest($this->keyPair, $method, route($route, $commit))->assertNotFound();

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
})->with([
    'pin' => ['PUT', 'api-commit-pin.update'],
    'unpin' => ['DELETE', 'api-commit-pin.destroy'],
]);

it('requires a signed request', function (string $method, string $route): void {
    $commit = Commit::factory()->for($this->user)->create();

    $this->json($method, route($route, $commit))->assertUnauthorized();

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
})->with([
    'pin' => ['PUT', 'api-commit-pin.update'],
    'unpin' => ['DELETE', 'api-commit-pin.destroy'],
]);
