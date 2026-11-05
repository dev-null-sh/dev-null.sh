<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Actions\UpdateDevicePushToken;
use App\Enums\PushPlatform;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

it('saves the token even when it left the phone after the request was authenticated', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    signedApiRequest($keyPair, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token', 'platform' => 'ios'])->assertNoContent();

    $cleared = false;
    Event::listen('eloquent.retrieved: '.Device::class, function (Device $device) use (&$cleared): void {
        if (! $cleared) {
            $cleared = true;
            Device::query()->whereKey($device->id)->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);
        }
    });

    signedApiRequest($keyPair, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-token', 'platform' => 'ios'])->assertNoContent();

    expect($user->devices()->sole()->push_token)->toBe('fcm-token');
});

it('moves the token again when another phone takes it in the middle of the move', function (): void {
    $other = Device::factory()->create();
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    $taken = false;
    DB::listen(function (QueryExecuted $query) use (&$taken, $other): void {
        if (! $taken && str_starts_with($query->sql, 'update "devices"') && str_contains($query->sql, 'where "push_token_hash" = ?')) {
            $taken = true;
            Device::query()->whereKey($other->id)->update(['push_token_hash' => hash('sha256', 'fcm-shared')]);
        }
    });

    signedApiRequest($keyPair, 'PUT', route('api-device-push-token.update'), ['token' => 'fcm-shared', 'platform' => 'ios'])->assertNoContent();

    expect($taken)->toBeTrue()
        ->and(Device::query()->whereNotNull('push_token_hash')->sole()->user_id)->toBe($user->id);
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

it('stops pushing even when a token arrived after the request was authenticated', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    $arrived = false;
    Event::listen('eloquent.retrieved: '.Device::class, function (Device $device) use (&$arrived): void {
        if (! $arrived) {
            $arrived = true;
            resolve(UpdateDevicePushToken::class)->handle(Device::query()->findOrFail($device->id), 'fcm-token', PushPlatform::Ios);
        }
    });

    signedApiRequest($keyPair, 'DELETE', route('api-device-push-token.destroy'))->assertNoContent();

    expect($arrived)->toBeTrue()
        ->and($user->devices()->sole()->push_token_hash)->toBeNull();
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
