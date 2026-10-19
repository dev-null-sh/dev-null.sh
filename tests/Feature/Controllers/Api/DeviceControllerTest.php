<?php

declare(strict_types=1);

use App\Actions\CreateAuthorizationCode;
use App\Actions\CreateDevice;
use App\Dto\AuthorizationCode;
use App\Dto\MobileAuthorization;
use App\Models\Device;
use App\Models\SshKey;
use App\Models\User;
use App\Services\AppleIdentityTokens;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\AppleTokenIssuer;
use Tests\Support\SshKeyPair;

beforeEach(function (): void {
    $this->user = User::factory()->create(['username' => 'octocat']);
    $this->keyPair = SshKeyPair::generate();
    $this->verifier = str_repeat('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk', 2);

    $this->code = resolve(CreateAuthorizationCode::class)->handle($this->user, new MobileAuthorization(
        $this->keyPair->publicKey->fingerprint(), 'devnull on iPhone', AuthorizationCode::challengeFor($this->verifier), 'state', 'devnull://auth/callback',
    ));
});

it('approves the key that redeems the code as a device, and answers like me', function (): void {
    $response = signedApiRequest($this->keyPair, 'POST', route('api-device.store'), ['code' => $this->code, 'code_verifier' => $this->verifier]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.username', 'octocat')
        ->assertJsonPath('data.lane', $this->user->lane())
        ->assertJsonPath('data.commits_count', 0)
        ->assertJsonPath('unread_notifications_count', 0)
        ->assertJsonPath('apple_linked', false);

    $device = Device::query()->sole();

    expect($device->user_id)->toBe($this->user->id)
        ->and($device->fingerprint)->toBe($this->keyPair->publicKey->fingerprint())
        ->and($device->name)->toBe('devnull on iPhone')
        ->and($device->approved_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and(Cache::has(AuthorizationCode::keyFor($this->code)))->toBeFalse();

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);

    Http::assertNothingSent();
});

it('never needs the key to be on github', function (): void {
    expect(SshKey::query()->exists())->toBeFalse();

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), ['code' => $this->code, 'code_verifier' => $this->verifier])
        ->assertOk();
});

it('refuses a code it cannot redeem, the same way whatever is wrong with it', function (Closure $request): void {
    $response = $request($this);

    $response->assertBadRequest()
        ->assertExactJson([
            'message' => 'This sign-in has expired or was already used. Sign in again in the app.',
            'code' => 'invalid_grant',
        ]);

    expect(Device::query()->exists())->toBeFalse();
})->with([
    'a wrong verifier' => fn ($test): TestResponse => signedApiRequest($test->keyPair, 'POST', route('api-device.store'), ['code' => $test->code, 'code_verifier' => str_repeat('a', 43)]),
    'an unknown code' => fn ($test): TestResponse => signedApiRequest($test->keyPair, 'POST', route('api-device.store'), ['code' => 'another-code', 'code_verifier' => $test->verifier]),
    'another key' => fn ($test): TestResponse => signedApiRequest(SshKeyPair::generate(), 'POST', route('api-device.store'), ['code' => $test->code, 'code_verifier' => $test->verifier]),
    'an expired code' => function ($test): TestResponse {
        $test->travel(AuthorizationCode::EXPIRES_AFTER_MINUTES)->minutes();

        return signedApiRequest($test->keyPair, 'POST', route('api-device.store'), ['code' => $test->code, 'code_verifier' => $test->verifier]);
    },
    'a code whose user was deleted' => function ($test): TestResponse {
        $test->user->delete();

        return signedApiRequest($test->keyPair, 'POST', route('api-device.store'), ['code' => $test->code, 'code_verifier' => $test->verifier]);
    },
]);

it('refuses a code handed out for another key, and leaves it for that key', function (): void {
    $other = SshKeyPair::generate();

    $code = resolve(CreateAuthorizationCode::class)->handle(User::factory()->create(), new MobileAuthorization(
        $other->publicKey->fingerprint(), 'devnull on Pixel', AuthorizationCode::challengeFor($this->verifier), 'state', 'devnull://auth/callback',
    ));

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), ['code' => $code, 'code_verifier' => $this->verifier])
        ->assertBadRequest()
        ->assertJsonPath('code', 'invalid_grant');

    signedApiRequest($other, 'POST', route('api-device.store'), ['code' => $code, 'code_verifier' => $this->verifier])
        ->assertOk();
});

it('redeems a code only once', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), ['code' => $this->code, 'code_verifier' => $this->verifier])->assertOk();

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), ['code' => $this->code, 'code_verifier' => $this->verifier])
        ->assertBadRequest()
        ->assertJsonPath('code', 'invalid_grant');
});

it('approves again a key approved for someone else, for the user who approved it now', function (): void {
    $previous = Device::factory()->withPublicKey($this->keyPair->publicKey)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), ['code' => $this->code, 'code_verifier' => $this->verifier])
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);

    expect($previous->refresh()->user_id)->toBe($this->user->id);
});

it('refuses a code that was redeemed by another request in the meantime', function (): void {
    // The code is checked, then redeemed by another request before this one approves its device...
    Event::listen(function (CacheHit $event): void {
        if ($event->key === AuthorizationCode::keyFor($this->code) && is_array($event->value)) {
            resolve(CreateDevice::class)->handle(AuthorizationCode::fromArray($event->key, $event->value));
        }
    });

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), ['code' => $this->code, 'code_verifier' => $this->verifier])
        ->assertBadRequest()
        ->assertJsonPath('code', 'invalid_grant');

    expect(Device::query()->count())->toBe(1);
});

it('validates the code and the verifier', function (array $data, string $field): void {
    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no code' => [['code_verifier' => str_repeat('a', 43)], 'code'],
    'no verifier' => [['code' => 'the-code'], 'code_verifier'],
    'a short verifier' => [['code' => 'the-code', 'code_verifier' => str_repeat('a', 42)], 'code_verifier'],
    'a long verifier' => [['code' => 'the-code', 'code_verifier' => str_repeat('a', 129)], 'code_verifier'],
    'a verifier with other characters' => [['code' => 'the-code', 'code_verifier' => str_repeat('a', 42).'!'], 'code_verifier'],
]);

it('rejects a request that is not signed right', function (Closure $server, string $message): void {
    $body = json_encode(['code' => 'the-code', 'code_verifier' => $this->verifier], JSON_THROW_ON_ERROR);

    $response = $this->call('POST', route('api-device.store'), server: $server($this->keyPair, $body), content: $body);

    $response->assertUnauthorized()
        ->assertExactJson(['message' => $message, 'code' => 'invalid_signature']);

    expect(Device::query()->exists())->toBeFalse();
})->with([
    'no signature' => [fn (): array => ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], 'Malformed SSH signature.'],
    'a signature over another body' => [fn (SshKeyPair $keyPair): array => signApiRequest($keyPair, 'POST', route('api-device.store'), '{}'), 'Invalid signature.'],
    'an expired signature' => [fn (SshKeyPair $keyPair, string $body): array => signApiRequest($keyPair, 'POST', route('api-device.store'), $body, timestamp: now()->getTimestamp() - 301), 'Signature expired. Check that your clock is right.'],
]);

it('limits each key to 12 tries a minute, wherever they come from', function (): void {
    foreach (range(1, 12) as $attempt) {
        $server = [...signApiRequest($this->keyPair, 'POST', route('api-device.store'), '{"code":"wrong","code_verifier":"'.str_repeat('a', 43).'"}'), 'REMOTE_ADDR' => "203.0.113.{$attempt}"];

        $this->call('POST', route('api-device.store'), server: $server, content: '{"code":"wrong","code_verifier":"'.str_repeat('a', 43).'"}')->assertBadRequest();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), ['code' => $this->code, 'code_verifier' => $this->verifier])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', 60)
        ->assertExactJson(['message' => 'Too Many Attempts.', 'code' => 'rate_limited']);

    signedApiRequest(SshKeyPair::generate(), 'POST', route('api-device.store'), ['code' => $this->code, 'code_verifier' => $this->verifier])
        ->assertBadRequest();
});

it('limits each address to 60 tries a minute, before checking signatures', function (): void {
    foreach (range(1, 60) as $attempt) {
        $this->postJson(route('api-device.store'))->assertUnauthorized();
    }

    $this->postJson(route('api-device.store'))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');
});

it('signs in with the apple id the user linked, and approves the key as a device', function (): void {
    $issuer = AppleTokenIssuer::generate();
    fakeAppleKeys($issuer);
    $this->user->forceFill(['apple_id' => '001234.0a1b2c3d4e5f.1234'])->save();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-device.store'), [
        'apple_identity_token' => $issuer->token(),
        'nonce' => 'raw-nonce',
        'device_name' => 'devnull on iPad',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('apple_linked', true);

    $device = Device::query()->sole();

    expect($device->user_id)->toBe($this->user->id)
        ->and($device->fingerprint)->toBe($this->keyPair->publicKey->fingerprint())
        ->and($device->name)->toBe('devnull on iPad');

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))->assertOk();
});

it('never makes an account for an apple id nobody linked', function (): void {
    $issuer = AppleTokenIssuer::generate();
    fakeAppleKeys($issuer);

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), [
        'apple_identity_token' => $issuer->token(),
        'nonce' => 'raw-nonce',
        'device_name' => 'devnull on iPad',
    ])->assertNotFound()
        ->assertExactJson([
            'message' => 'No /dev/null account is linked to this Apple ID. Sign in with GitHub, then link Apple in settings.',
            'code' => 'apple_not_linked',
        ]);

    expect(Device::query()->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(1);
});

it('refuses an apple token apple did not make for the app and the nonce', function (): void {
    $issuer = AppleTokenIssuer::generate();
    fakeAppleKeys($issuer);
    $this->user->forceFill(['apple_id' => '001234.0a1b2c3d4e5f.1234'])->save();

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), [
        'apple_identity_token' => $issuer->token(),
        'nonce' => 'another-nonce',
        'device_name' => 'devnull on iPad',
    ])->assertUnprocessable()
        ->assertExactJson(['message' => 'Sign in with Apple did not work. Try again.', 'code' => 'invalid_apple_token']);

    expect(Device::query()->exists())->toBeFalse();
});

it('takes either a code or an apple token, with what each needs', function (array $data, string $field): void {
    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Device::query()->exists())->toBeFalse();
})->with([
    'neither' => [[], 'code'],
    'both' => [['code' => 'the-code', 'code_verifier' => str_repeat('a', 43), 'apple_identity_token' => 'token', 'nonce' => 'n', 'device_name' => 'd'], 'code'],
    'a token without a nonce' => [['apple_identity_token' => 'token', 'device_name' => 'devnull on iPad'], 'nonce'],
    'a token without a device name' => [['apple_identity_token' => 'token', 'nonce' => 'raw-nonce'], 'device_name'],
    'a long device name' => [['apple_identity_token' => 'token', 'nonce' => 'raw-nonce', 'device_name' => str_repeat('a', 101)], 'device_name'],
]);

it('counts apple sign-ins against the same limit per key', function (): void {
    $issuer = AppleTokenIssuer::generate();
    fakeAppleKeys($issuer);
    $data = ['apple_identity_token' => $issuer->token(), 'nonce' => 'raw-nonce', 'device_name' => 'devnull on iPad'];

    foreach (range(1, 12) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-device.store'), $data)->assertNotFound();
    }

    $this->user->forceFill(['apple_id' => '001234.0a1b2c3d4e5f.1234'])->save();

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), $data)
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');

    expect(Device::query()->exists())->toBeFalse();
});

it('approves nothing when apple cannot be reached to check the token', function (): void {
    Http::fake([AppleIdentityTokens::KeysUrl => Http::response(status: 500)]);
    $this->user->forceFill(['apple_id' => '001234.0a1b2c3d4e5f.1234'])->save();

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), [
        'apple_identity_token' => AppleTokenIssuer::generate()->token(),
        'nonce' => 'raw-nonce',
        'device_name' => 'devnull on iPad',
    ])->assertServiceUnavailable();

    expect(Device::query()->exists())->toBeFalse();
});

it('leaves a key approved before as it was when signing in with apple fails', function (string $nonce, int $status): void {
    $issuer = AppleTokenIssuer::generate();
    fakeAppleKeys($issuer);
    $previous = Device::factory()->withPublicKey($this->keyPair->publicKey)->create(['name' => 'devnull on iPhone']);

    signedApiRequest($this->keyPair, 'POST', route('api-device.store'), [
        'apple_identity_token' => $issuer->token(),
        'nonce' => $nonce,
        'device_name' => 'devnull on iPad',
    ])->assertStatus($status);

    expect(Device::query()->sole()->only(['id', 'user_id', 'name']))->toBe($previous->only(['id', 'user_id', 'name']));
})->with([
    'an apple id nobody linked' => ['raw-nonce', 404],
    'a bad token' => ['another-nonce', 422],
]);
