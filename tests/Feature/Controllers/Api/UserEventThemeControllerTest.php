<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('saves the event theme only for the user, and answers like me', function (string $choice): void {
    $other = User::factory()->create();
    Notification::factory()->for($this->user, 'recipient')->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-user-event-theme.update'), ['choice' => $choice, 'user_id' => $other->id])
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonMissingPath('data.event_theme')
        ->assertJsonPath('unread_notifications_count', 1);

    expect($this->user->refresh()->event_theme)->toBe($choice)
        ->and($other->refresh()->event_theme)->toBeNull();

    signedApiRequest($this->keyPair, 'GET', route('api-user-settings.show'))
        ->assertJsonPath('data.event_theme', $choice);
})->with(['auto', 'none', 'halloween', 'christmas', 'newyear']);

it('rejects missing or unknown choices without changing the saved one', function (array $data): void {
    $this->user->update(['event_theme' => 'christmas']);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-event-theme.update'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('choice');

    expect($this->user->refresh()->event_theme)->toBe('christmas');
})->with(['missing' => [[]], 'unknown' => [['choice' => 'easter']], 'array' => [['choice' => ['auto']]]]);

it('requires a signed request', function (): void {
    $this->putJson(route('api-user-event-theme.update'), ['choice' => 'none'])->assertUnauthorized();

    expect($this->user->refresh()->event_theme)->toBeNull();
});
