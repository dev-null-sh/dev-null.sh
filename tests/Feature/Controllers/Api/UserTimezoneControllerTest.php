<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create(['timezone' => 'Europe/Lisbon']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('may choose a timezone, and answers like me', function (): void {
    Notification::factory()->for($this->user, 'recipient')->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), ['timezone' => 'Europe/Budapest']);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.handle', $this->user->handle)
        ->assertJsonPath('unread_notifications_count', 1);

    expect($this->user->refresh())
        ->timezone->toBe('Europe/Budapest')
        ->show_local_time->toBeFalse();
});

it('may opt in to and out of showing the local time', function (bool $show): void {
    $this->user->update(['show_local_time' => ! $show]);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), ['timezone' => 'Europe/Budapest', 'show_local_time' => $show])
        ->assertOk();

    expect($this->user->refresh()->show_local_time)->toBe($show);
})->with([true, false]);

it('never shows the local time without a timezone', function (): void {
    $this->user->update(['show_local_time' => true]);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), ['timezone' => null, 'show_local_time' => true])
        ->assertOk();

    expect($this->user->refresh())
        ->timezone->toBeNull()
        ->show_local_time->toBeFalse();
});

it('may clear the timezone to follow the device', function (mixed $timezone): void {
    signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), ['timezone' => $timezone])->assertOk();

    expect($this->user->refresh()->timezone)->toBeNull();
})->with([null, '']);

it('rejects unknown timezones and a show_local_time that is not a boolean', function (array $data, string $field): void {
    signedApiRequest($this->keyPair, 'PUT', route('api-user-timezone.update'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($this->user->refresh()->timezone)->toBe('Europe/Lisbon');
})->with([
    'an unknown timezone' => [['timezone' => 'Mars/Olympus_Mons'], 'timezone'],
    'an offset' => [['timezone' => 'GMT+2'], 'timezone'],
    'a lowercase timezone' => [['timezone' => 'europe/budapest'], 'timezone'],
    'a number' => [['timezone' => 42], 'timezone'],
    'a list' => [['timezone' => ['Europe/Budapest']], 'timezone'],
    'no timezone' => [[], 'timezone'],
    'a show_local_time that is not a boolean' => [['timezone' => 'Europe/Budapest', 'show_local_time' => 'maybe'], 'show_local_time'],
]);

it('requires a signed request', function (): void {
    $this->putJson(route('api-user-timezone.update'), ['timezone' => 'Europe/Budapest'])->assertUnauthorized();

    expect($this->user->refresh()->timezone)->toBe('Europe/Lisbon');
});
