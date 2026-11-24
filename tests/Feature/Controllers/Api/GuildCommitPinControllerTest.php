<?php

declare(strict_types=1);

use App\Actions\UpdateGuildCommitPin;
use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Models\UserTimeout;
use App\Services\Features;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::Guilds, true, adminOnly: false);

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->rust = Guild::factory()->create(['handle' => 'rust', 'user_id' => $this->user->id]);
});

it('pins and unpins board commits for the moderator, keeping their profile pin', function (): void {
    $first = Commit::factory()->for($this->rust)->create();
    $next = Commit::factory()->create(['body' => 'Learning #rust']);
    $profilePin = Commit::factory()->for($this->user)->create();
    $this->user->update(['pinned_commit_id' => $profilePin->id]);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-commit-pin.update', ['rust', $first]))->assertNoContent();
    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))->assertJsonPath('pinned_commit.id', $first->id);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-commit-pin.update', ['rust', $next]))->assertNoContent();
    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-commit-pin.destroy', ['rust', $first]))->assertNoContent();
    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))->assertJsonPath('pinned_commit.id', $next->id);

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-commit-pin.destroy', ['rust', $next]))->assertNoContent();

    expect($this->rust->fresh()->pinned_commit_id)->toBeNull()
        ->and($this->user->fresh()->pinned_commit_id)->toBe($profilePin->id);
    signedApiRequest($this->keyPair, 'GET', route('api-guild.show', 'rust'))->assertJsonPath('pinned_commit', null);
});

it('clears a pin the board no longer shows, but does not pin it again', function (string $change): void {
    $commit = Commit::factory()->create(['body' => '#rust']);
    $this->rust->update(['pinned_commit_id' => $commit->id]);
    $change === 'deleted' ? $commit->delete() : $commit->update(['body' => 'No guild hashtag']);

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-commit-pin.update', ['rust', $commit]))->assertNotFound();
    expect($this->rust->fresh()->pinned_commit_id)->toBe($commit->id);

    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-commit-pin.destroy', ['rust', $commit]))->assertNoContent();

    expect($this->rust->fresh()->pinned_commit_id)->toBeNull();
})->with(['deleted', 'edited']);

it('does not find the pin for anyone but the moderator, or for commits the board does not show', function (string $case): void {
    $commit = match ($case) {
        'reply' => Commit::factory()->for(Commit::factory()->for($this->rust), 'parent')->create(['body' => '#rust']),
        'other board' => Commit::factory()->for(Guild::factory())->create(['body' => '#rust']),
        'unrelated' => Commit::factory()->create(['body' => '`#rust` #rustaceans']),
        default => Commit::factory()->for($this->rust)->create(),
    };
    if ($case === 'admin') {
        config(['app.admins' => [$this->user->handle]]);
        $this->rust->update(['user_id' => User::factory()->create()->id]);
    }

    if ($case === 'guilds off') {
        resolve(Features::class)->update(Feature::Guilds, false);
    }

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-commit-pin.update', ['rust', $commit]))->assertNotFound();
    signedApiRequest($this->keyPair, 'DELETE', route('api-guild-commit-pin.destroy', ['rust', $commit]))->assertNotFound();

    expect($this->rust->fresh()->pinned_commit_id)->toBeNull();
})->with(['admin', 'reply', 'other board', 'unrelated', 'guilds off']);

it('requires a signed request', function (string $method, string $route): void {
    $commit = Commit::factory()->for($this->rust)->create();

    $this->json($method, route($route, ['rust', $commit]))->assertUnauthorized();
})->with([
    'pin' => ['PUT', 'api-guild-commit-pin.update'],
    'unpin' => ['DELETE', 'api-guild-commit-pin.destroy'],
]);

it('does not find the guild for a moderator removed after authorization', function (string $method, string $route, bool $pinned): void {
    $commit = Commit::factory()->for($this->rust)->create();
    $this->rust->update(['pinned_commit_id' => $pinned ? $commit->id : null]);
    $this->app->beforeResolving(UpdateGuildCommitPin::class, fn () => $this->rust->update(['user_id' => null]));

    signedApiRequest($this->keyPair, $method, route($route, ['rust', $commit]))->assertNotFound();

    expect($this->rust->fresh()->pinned_commit_id)->toBe($pinned ? $commit->id : null);
})->with([
    'pin' => ['PUT', 'api-guild-commit-pin.update', false],
    'unpin' => ['DELETE', 'api-guild-commit-pin.destroy', true],
]);

it('keeps the current pin when the commit becomes unavailable after authorization', function (string $change): void {
    $old = Commit::factory()->for($this->rust)->create();
    $this->rust->update(['pinned_commit_id' => $old->id]);
    $target = Commit::factory()->create(['body' => '#rust']);
    $this->app->beforeResolving(UpdateGuildCommitPin::class, fn () => match ($change) {
        'erased' => $target->forceDelete(),
        'deleted' => $target->delete(),
        'edited' => $target->update(['body' => 'No guild hashtag']),
    });

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-commit-pin.update', ['rust', $target]))
        ->assertNotFound()->assertJsonPath('code', 'commit_unavailable');

    expect($this->rust->fresh()->pinned_commit_id)->toBe($old->id);
})->with(['erased', 'deleted', 'edited']);

it('refuses pin changes while the moderator is in timeout', function (string $method, string $route): void {
    $commit = Commit::factory()->for($this->rust)->create();
    $this->rust->update(['pinned_commit_id' => $commit->id]);
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, $method, route($route, ['rust', $commit]))
        ->assertForbidden()->assertJsonPath('code', 'timed_out');

    expect($this->rust->fresh()->pinned_commit_id)->toBe($commit->id);
})->with([
    'pin' => ['PUT', 'api-guild-commit-pin.update'],
    'unpin' => ['DELETE', 'api-guild-commit-pin.destroy'],
]);

it('does not tell a user in timeout who does not moderate the guild about the timeout', function (): void {
    $commit = Commit::factory()->for($this->rust)->create();
    $this->rust->update(['user_id' => User::factory()->create()->id]);
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-guild-commit-pin.update', ['rust', $commit]))->assertNotFound();
});
