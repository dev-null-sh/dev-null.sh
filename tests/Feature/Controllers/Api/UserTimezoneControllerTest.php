<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create(['timezone' => 'Europe/Budapest']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('may choose a timezone, and answers like me', function (): void {
    Notification::factory()->for($this->user, 'recipient')->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), ['timezone' => 'America/Sao_Paulo']);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.timezone', 'America/Sao_Paulo')
        ->assertJsonPath('unread_notifications_count', 1);

    expect($this->user->refresh()->timezone)->toBe('America/Sao_Paulo');
});

it('may unset the timezone', function (mixed $timezone): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), ['timezone' => $timezone]);

    $response->assertOk()
        ->assertJsonPath('data.timezone', null);

    expect($this->user->refresh()->timezone)->toBeNull();
})->with([null, '']);

it('rejects unknown timezones', function (mixed $timezone): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), ['timezone' => $timezone]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');

    expect($this->user->refresh()->timezone)->toBe('Europe/Budapest');
})->with(['Mars/Olympus_Mons', 'GMT+2', 'europe/budapest', 42, [['Europe/Budapest']]]);

it('requires the timezone to be present', function (): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');

    expect($this->user->refresh()->timezone)->toBe('Europe/Budapest');
});

it('requires a signed request', function (): void {
    $this->putJson(route('api-user-timezone.update'), ['timezone' => 'America/Sao_Paulo'])
        ->assertUnauthorized();

    expect($this->user->refresh()->timezone)->toBe('Europe/Budapest');
});
