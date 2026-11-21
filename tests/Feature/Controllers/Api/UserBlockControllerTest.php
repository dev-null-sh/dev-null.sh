<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('may block a user, which ends the follows both ways, and answers with them and that they are blocked', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $this->user->following()->attach($user);
    $user->following()->attach($this->user);

    $response = signedApiRequest($this->keyPair, 'POST', route('api-user-block.store', $user));

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.handle', 'octocat')
        ->assertJsonPath('data.lane', $user->lane())
        ->assertJsonPath('is_blocking', true);

    expect($this->user->isBlocking($user))->toBeTrue()
        ->and($this->user->isFollowing($user))->toBeFalse()
        ->and($user->isFollowing($this->user))->toBeFalse()
        ->and($user->notifications()->count())->toBe(0);
});

it('may block a user who blocks them, as on the web', function (): void {
    $user = User::factory()->create();

    $user->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-user-block.store', $user))
        ->assertOk()
        ->assertJsonPath('is_blocking', true);

    expect($this->user->isBlocking($user))->toBeTrue()
        ->and($user->isBlocking($this->user))->toBeTrue();
});

it('keeps one block when a user is blocked again', function (): void {
    $user = User::factory()->create();

    $this->user->blocking()->attach($user);

    signedApiRequest($this->keyPair, 'POST', route('api-user-block.store', $user))
        ->assertOk()
        ->assertJsonPath('is_blocking', true);

    expect($this->user->blocking()->count())->toBe(1);
});

it('may not block themselves', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-user-block.store', $this->user))->assertForbidden();

    expect($this->user->blocking()->count())->toBe(0);
});

it('may unblock a user, which does not bring back the follows, and answers that they are not blocked', function (): void {
    $user = User::factory()->create();

    $this->user->following()->attach($user);
    signedApiRequest($this->keyPair, 'POST', route('api-user-block.store', $user))->assertOk();

    signedApiRequest($this->keyPair, 'DELETE', route('api-user-block.destroy', $user))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('is_blocking', false);

    expect($this->user->isBlocking($user))->toBeFalse()
        ->and($this->user->isFollowing($user))->toBeFalse();
});

it('may unblock a user who was not blocked', function (): void {
    $user = User::factory()->create();

    signedApiRequest($this->keyPair, 'DELETE', route('api-user-block.destroy', $user))
        ->assertOk()
        ->assertJsonPath('is_blocking', false);
});

it('returns not found for unknown handles', function (string $method): void {
    signedApiRequest($this->keyPair, $method, 'api/v1/users/missing/block')->assertNotFound();
})->with(['POST', 'DELETE']);

it('lists the people the user blocks, last blocked first, as Settings → Safety does', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $other = User::factory()->create();

    Date::setTestNow(now()->subHour());
    $this->user->blocking()->attach($first);
    Date::setTestNow();
    $this->user->blocking()->attach($second);
    $other->blocking()->attach($first);

    signedApiRequest($this->keyPair, 'GET', route('api-user-block.index'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$second->id, $first->id])
        ->assertJsonPath('data.0.handle', $second->handle)
        ->assertJsonPath('data.0.name', $second->name)
        ->assertJsonPath('data.0.avatar_url', $second->avatar_url)
        ->assertJsonPath('data.0.lane', $second->lane())
        ->assertJsonMissingPath('data.0.pivot')
        ->assertJsonPath('meta.next_cursor', null);
});

it('pages through the people the user blocks, 20 at a time, even when they were blocked at once', function (): void {
    $blocked = User::factory()->count(25)->create();

    $this->user->blocking()->attach($blocked->pluck('id'));

    $expected = $blocked->sortByDesc('id')->pluck('id')->values();

    $first = signedApiRequest($this->keyPair, 'GET', route('api-user-block.index'))
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('data.*.id', $expected->take(20)->all());

    signedApiRequest($this->keyPair, 'GET', route('api-user-block.index', ['cursor' => $first->json('meta.next_cursor')]))
        ->assertOk()
        ->assertJsonPath('data.*.id', $expected->slice(20)->values()->all())
        ->assertJsonPath('meta.next_cursor', null);
});

it('lists no one when the user blocks no one', function (): void {
    signedApiRequest($this->keyPair, 'GET', route('api-user-block.index'))
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('rejects requests that are not signed', function (string $method, string $route): void {
    $user = User::factory()->create();

    $this->json($method, route($route, $route === 'api-user-block.index' ? [] : [$user]))->assertUnauthorized();

    expect($user->blockers()->count())->toBe(0);
})->with([
    ['GET', 'api-user-block.index'],
    ['POST', 'api-user-block.store'],
    ['DELETE', 'api-user-block.destroy'],
]);
