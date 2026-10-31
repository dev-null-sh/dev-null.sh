<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Models\User;
use App\Models\UserTimeout;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->live = ['live_platform' => LivePlatform::YouTube, 'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'live_ends_at' => now()->addHour()];
});

it('goes live for a few hours, and answers as the current user endpoint does', function (string $url, LivePlatform $platform, string $stream): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-live.update'), ['url' => " {$url} ", 'hours' => 3]);

    $response->assertOk()
        ->assertJsonPath('data.live.platform', $platform->value)
        ->assertJsonPath('data.live.url', $stream)
        ->assertJsonPath('data.live.ends_at', now()->addHours(3)->toIso8601ZuluString())
        ->assertExactJson(signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))->json());

    expect($this->user->refresh())
        ->live_platform->toBe($platform)
        ->live_url->toBe($stream);
})->with([
    'YouTube' => ['https://youtu.be/dQw4w9WgXcQ', LivePlatform::YouTube, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'Twitch' => ['https://m.twitch.tv/octocat', LivePlatform::Twitch, 'https://www.twitch.tv/octocat'],
]);

it('moves to another stream and restarts the hours while live', function (): void {
    $this->user->update($this->live);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-live.update'), ['url' => 'https://www.twitch.tv/octocat', 'hours' => 2])
        ->assertOk()
        ->assertJsonPath('data.live.url', 'https://www.twitch.tv/octocat')
        ->assertJsonPath('data.live.ends_at', now()->addHours(2)->toIso8601ZuluString());
});

it('rejects links that are not a stream', function (array $payload): void {
    signedApiRequest($this->keyPair, 'PUT', route('api-user-live.update'), [...$payload, 'hours' => 2])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    expect($this->user->refresh()->live_url)->toBeNull();
})->with([
    'missing' => fn (): array => [],
    'not a link' => fn (): array => ['url' => 'youtube'],
    'not a stream' => fn (): array => ['url' => 'https://example.com/live'],
    'twitch page' => fn (): array => ['url' => 'https://www.twitch.tv/directory'],
]);

it('takes between one and twelve hours', function (mixed $hours): void {
    signedApiRequest($this->keyPair, 'PUT', route('api-user-live.update'), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => $hours])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hours');

    expect($this->user->refresh()->live_url)->toBeNull();
})->with([
    'missing' => null,
    'none' => 0,
    'too many' => User::MAX_LIVE_HOURS + 1,
    'not a number' => 'all day',
]);

it('ends the stream, and answers as the current user endpoint does', function (): void {
    $this->user->update($this->live);

    signedApiRequest($this->keyPair, 'DELETE', route('api-user-live.destroy'))
        ->assertOk()
        ->assertJsonPath('data.live', null)
        ->assertExactJson(signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))->json());

    expect($this->user->refresh())
        ->live_platform->toBeNull()
        ->live_url->toBeNull()
        ->live_ends_at->toBeNull();
});

it('stops a user in timeout from going live, but lets them end their stream', function (): void {
    $this->user->update($this->live);
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-user-live.update'), ['url' => 'https://www.twitch.tv/buymycourse', 'hours' => 12])
        ->assertForbidden()
        ->assertJsonPath('code', 'timed_out');

    expect($this->user->refresh()->live_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    signedApiRequest($this->keyPair, 'DELETE', route('api-user-live.destroy'))->assertOk();

    expect($this->user->refresh()->live_url)->toBeNull();
});

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-live.update'), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => 2])->assertUnauthorized();
    $this->deleteJson(route('api-user-live.destroy'))->assertUnauthorized();
});
