<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('may mute a user, and answers with them and that they are muted', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $this->user->following()->attach($user);
    $user->following()->attach($this->user);

    $response = signedApiRequest($this->keyPair, 'POST', route('api-user-mute.store', $user));

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.handle', 'octocat')
        ->assertJsonPath('data.lane', $user->lane())
        ->assertJsonPath('is_muting', true);

    expect($this->user->isMuting($user))->toBeTrue()
        ->and($this->user->isFollowing($user))->toBeTrue()
        ->and($user->isFollowing($this->user))->toBeTrue()
        ->and($user->notifications()->count())->toBe(0);
});

it('keeps one mute when a user is muted again', function (): void {
    $user = User::factory()->create();

    $this->user->muting()->attach($user);

    signedApiRequest($this->keyPair, 'POST', route('api-user-mute.store', $user))
        ->assertOk()
        ->assertJsonPath('is_muting', true);

    expect($this->user->muting()->count())->toBe(1);
});

it('may not mute themselves', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-user-mute.store', $this->user))->assertForbidden();

    expect($this->user->muting()->count())->toBe(0);
});

it('may unmute a user, and answers that they are not muted', function (): void {
    $user = User::factory()->create();

    $this->user->muting()->attach($user);

    signedApiRequest($this->keyPair, 'DELETE', route('api-user-mute.destroy', $user))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('is_muting', false);

    expect($this->user->isMuting($user))->toBeFalse();
});

it('may unmute a user who was not muted', function (): void {
    $user = User::factory()->create();

    signedApiRequest($this->keyPair, 'DELETE', route('api-user-mute.destroy', $user))
        ->assertOk()
        ->assertJsonPath('is_muting', false);
});

it('returns not found for unknown handles', function (string $method): void {
    signedApiRequest($this->keyPair, $method, 'api/v1/users/missing/mute')->assertNotFound();
})->with(['POST', 'DELETE']);

it('lists the people the user mutes, last muted first, as Settings → Safety does', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $other = User::factory()->create();

    Date::setTestNow(now()->subHour());
    $this->user->muting()->attach($first);
    Date::setTestNow();
    $this->user->muting()->attach($second);
    $other->muting()->attach($first);

    signedApiRequest($this->keyPair, 'GET', route('api-user-mute.index'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$second->id, $first->id])
        ->assertJsonPath('data.0.handle', $second->handle)
        ->assertJsonPath('data.0.name', $second->name)
        ->assertJsonPath('data.0.avatar_url', $second->avatar_url)
        ->assertJsonPath('data.0.lane', $second->lane())
        ->assertJsonMissingPath('data.0.pivot')
        ->assertJsonPath('meta.next_cursor', null);
});

it('pages through the people the user mutes, 20 at a time, even when they were muted at once', function (): void {
    $muted = User::factory()->count(25)->create();

    $this->user->muting()->attach($muted->pluck('id'));

    $expected = $muted->sortByDesc('id')->pluck('id')->values();

    $first = signedApiRequest($this->keyPair, 'GET', route('api-user-mute.index'))
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('data.*.id', $expected->take(20)->all());

    signedApiRequest($this->keyPair, 'GET', route('api-user-mute.index', ['cursor' => $first->json('meta.next_cursor')]))
        ->assertOk()
        ->assertJsonPath('data.*.id', $expected->slice(20)->values()->all())
        ->assertJsonPath('meta.next_cursor', null);
});

it('lists no one when the user mutes no one', function (): void {
    signedApiRequest($this->keyPair, 'GET', route('api-user-mute.index'))
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('rejects requests that are not signed', function (string $method, string $route): void {
    $user = User::factory()->create();

    $this->json($method, route($route, $route === 'api-user-mute.index' ? [] : [$user]))->assertUnauthorized();

    expect($user->mutedBy()->count())->toBe(0);
})->with([
    ['GET', 'api-user-mute.index'],
    ['POST', 'api-user-mute.store'],
    ['DELETE', 'api-user-mute.destroy'],
]);
