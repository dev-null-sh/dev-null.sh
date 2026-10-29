<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('turns automatic translation on or off only for the user, and answers like me', function (bool $enabled): void {
    $this->user->update(['automatic_translation' => ! $enabled]);
    $other = User::factory()->create(['automatic_translation' => ! $enabled]);
    Notification::factory()->for($this->user, 'recipient')->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-automatic-translation.update'), ['enabled' => $enabled, 'user_id' => $other->id]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonMissingPath('data.automatic_translation')
        ->assertJsonPath('unread_notifications_count', 1);

    expect($this->user->refresh()->automatic_translation)->toBe($enabled)
        ->and($other->refresh()->automatic_translation)->toBe(! $enabled);

    signedApiRequest($this->keyPair, 'GET', route('api-user-settings.show'))
        ->assertJsonPath('data.automatic_translation', $enabled);
})->with([true, false]);

it('rejects missing or invalid preferences without changing the saved choice', function (array $data): void {
    $this->user->update(['automatic_translation' => false]);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-automatic-translation.update'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    expect($this->user->refresh()->automatic_translation)->toBeFalse();
})->with(['missing' => [[]], 'null' => [['enabled' => null]], 'text' => [['enabled' => 'invalid']], 'array' => [['enabled' => []]]]);

it('requires a signed request', function (): void {
    $this->putJson(route('api-user-automatic-translation.update'), ['enabled' => false])->assertUnauthorized();

    expect($this->user->refresh()->automatic_translation)->toBeNull();
});
