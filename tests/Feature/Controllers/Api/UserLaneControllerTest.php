<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create(['id' => '3a788e93-4cf7-4819-9377-94be2f329563', 'preferred_lane' => 2]);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('may choose a lane, and answers like me', function (int $lane): void {
    Notification::factory()->for($this->user, 'recipient')->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-lane.update'), ['lane' => $lane]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.lane', $lane)
        ->assertJsonPath('data.preferred_lane', $lane)
        ->assertJsonPath('data.automatic_lane', 4)
        ->assertJsonPath('unread_notifications_count', 1);

    expect($this->user->refresh()->preferred_lane)->toBe($lane);
})->with([0, 1, 3, 4, 5]);

it('may go back to the automatic lane', function (): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-lane.update'), ['lane' => null]);

    $response->assertOk()
        ->assertJsonPath('data.lane', 4)
        ->assertJsonPath('data.preferred_lane', null)
        ->assertJsonPath('data.automatic_lane', 4);

    expect($this->user->refresh()->preferred_lane)->toBeNull();
});

it('treats an empty lane as automatic', function (): void {
    signedApiRequest($this->keyPair, 'PUT', route('api-user-lane.update'), ['lane' => ''])->assertOk();

    expect($this->user->refresh()->preferred_lane)->toBeNull();
});

it('rejects lanes outside the palette', function (mixed $lane): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-lane.update'), ['lane' => $lane]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('lane');

    expect($this->user->refresh()->preferred_lane)->toBe(2);
})->with([-1, 6, 1.5, 'teal', '#ff0000', [[1]]]);

it('may choose a custom color, as the web does', function (): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-lane.update'), ['lane' => null, 'color' => '#1a2b3c']);

    $response->assertOk()
        ->assertJsonPath('data.lane', '#1a2b3c')
        ->assertJsonPath('data.preferred_lane', '#1a2b3c')
        ->assertJsonPath('data.automatic_lane', 4);

    expect($this->user->refresh()->lane_color)->toBe('#1a2b3c')
        ->and($this->user->preferred_lane)->toBeNull();
});

it('drops the custom color for a lane from the palette or the automatic one', function (?int $lane): void {
    $this->user->update(['lane_color' => '#1a2b3c']);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-lane.update'), ['lane' => $lane])
        ->assertOk()
        ->assertJsonPath('data.preferred_lane', $lane);

    expect($this->user->refresh()->lane_color)->toBeNull();
})->with([1, null]);

it('rejects colors that are not six digit hex, or that come with a lane', function (array $data): void {
    $this->user->update(['lane_color' => '#1a2b3c']);

    signedApiRequest($this->keyPair, 'PUT', route('api-user-lane.update'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('color');

    expect($this->user->refresh()->lane_color)->toBe('#1a2b3c');
})->with([
    [['lane' => null, 'color' => '#1A2B3C']],
    [['lane' => null, 'color' => '#fff']],
    [['lane' => null, 'color' => 'red']],
    [['lane' => 1, 'color' => '#1a2b3c']],
]);

it('requires the lane to be present', function (): void {
    $response = signedApiRequest($this->keyPair, 'PUT', route('api-user-lane.update'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('lane');

    expect($this->user->refresh()->preferred_lane)->toBe(2);
});

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-lane.update'), ['lane' => 1])->assertUnauthorized();

    expect($this->user->refresh()->preferred_lane)->toBe(2);
});
