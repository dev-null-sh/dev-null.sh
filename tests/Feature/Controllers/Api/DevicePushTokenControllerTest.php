<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Enums\PushPlatform;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\SshKeyPair;

it('saves the push token for the phone that signed the request, encrypted', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token-1', 'platform' => 'ios'])
        ->assertNoContent();

    $device = $user->devices()->sole();

    expect($device->push_token)->toBe('fcm-token-1')
        ->and($device->push_platform)->toBe(PushPlatform::Ios)
        ->and($device->push_token_hash)->toBe(hash('sha256', 'fcm-token-1'))
        ->and(DB::table('devices')->value('push_token'))->not->toContain('fcm-token-1');
});

it('replaces the token when the phone gets a new one', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token-1', 'platform' => 'android'])->assertNoContent();
    signedApiRequest($keyPair, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token-2', 'platform' => 'android'])->assertNoContent();

    expect($user->devices()->sole())
        ->push_token->toBe('fcm-token-2')
        ->push_platform->toBe(PushPlatform::Android);
});

it("keeps a token for each of the user's phones", function (): void {
    $user = User::factory()->create();
    $iphone = apiKeyPairFor($user);
    $pixel = apiKeyPairFor($user);

    signedApiRequest($iphone, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-iphone', 'platform' => 'ios'])->assertNoContent();
    signedApiRequest($pixel, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-pixel', 'platform' => 'android'])->assertNoContent();

    expect($user->devices()->orderBy('push_platform')->pluck('push_token')->all())->toBe(['fcm-pixel', 'fcm-iphone']);
});

it('moves a token to the account that sent it last', function (): void {
    $before = User::factory()->create();
    $after = User::factory()->create();

    signedApiRequest(apiKeyPairFor($before), 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-shared', 'platform' => 'ios'])->assertNoContent();
    signedApiRequest(apiKeyPairFor($after), 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-shared', 'platform' => 'ios'])->assertNoContent();

    expect($before->devices()->sole())
        ->push_token->toBeNull()
        ->push_token_hash->toBeNull()
        ->push_platform->toBeNull()
        ->and($after->devices()->sole()->push_token)->toBe('fcm-shared');
});

it('moves a token to the device the phone signed in as again', function (): void {
    $user = User::factory()->create();
    $old = apiKeyPairFor($user);
    $new = apiKeyPairFor($user);

    signedApiRequest($old, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token', 'platform' => 'android'])->assertNoContent();
    signedApiRequest($new, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token', 'platform' => 'android'])->assertNoContent();

    expect(Device::query()->whereNotNull('push_token_hash')->sole()->fingerprint)->toBe($new->publicKey->fingerprint());
});

it('refuses a token or platform it can not push to', function (array $data, string $field): void {
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-device-push-token.update'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($user->devices()->sole()->push_token)->toBeNull();
})->with([
    'no token' => [['platform' => 'ios'], 'token'],
    'a token that is not a string' => [['token' => 42, 'platform' => 'ios'], 'token'],
    'a token too long' => [['token' => str_repeat('a', 4097), 'platform' => 'ios'], 'token'],
    'no platform' => [['token' => 'fcm-token'], 'platform'],
    'another platform' => [['token' => 'fcm-token', 'platform' => 'windows'], 'platform'],
]);

it('stops pushing to the phone that signed the request', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $other = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-this', 'platform' => 'ios'])->assertNoContent();
    signedApiRequest($other, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-other', 'platform' => 'ios'])->assertNoContent();

    signedApiRequest($keyPair, 'DELETE', route('api-device-push-token.destroy'))->assertNoContent();
    signedApiRequest($keyPair, 'DELETE', route('api-device-push-token.destroy'))->assertNoContent();

    expect($user->devices()->whereNotNull('push_token_hash')->pluck('push_token')->all())->toBe(['fcm-other']);
});

it("drops the token with the device when the device is revoked, and leaves someone else's alone", function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $someoneElse = User::factory()->create();
    $theirs = apiKeyPairFor($someoneElse);

    signedApiRequest($keyPair, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-mine', 'platform' => 'ios'])->assertNoContent();
    signedApiRequest($theirs, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-theirs', 'platform' => 'ios'])->assertNoContent();

    signedApiRequest($keyPair, 'DELETE', route('api-device.destroy', $someoneElse->devices()->sole()))->assertNotFound();
    signedApiRequest($keyPair, 'DELETE', route('api-device.destroy', $user->devices()->sole()))->assertNoContent();

    expect(Device::query()->whereNotNull('push_token_hash')->pluck('push_token')->all())->toBe(['fcm-theirs']);
});

it('drops the tokens with the account', function (): void {
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token', 'platform' => 'android'])->assertNoContent();

    resolve(DeleteUser::class)->handle($user);

    expect(Device::query()->whereNotNull('push_token_hash')->exists())->toBeFalse();
});

it('rejects requests that are not signed', function (string $method, string $route): void {
    $this->json($method, route($route), ['token' => 'fcm-token', 'platform' => 'ios'])->assertUnauthorized();
})->with([
    'saving' => ['PUT', 'api-device-push-token.update'],
    'deleting' => ['DELETE', 'api-device-push-token.destroy'],
]);

it('rejects a key nobody approved as a device', function (): void {
    signedApiRequest(SshKeyPair::generate(), 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token', 'platform' => 'ios'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'device_not_approved');

    expect(Device::query()->whereNotNull('push_token_hash')->exists())->toBeFalse();
});
