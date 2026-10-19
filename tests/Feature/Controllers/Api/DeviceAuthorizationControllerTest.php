<?php

declare(strict_types=1);

use App\Dto\DeviceAuthorization;
use App\Models\SshKey;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\SshKeyPair;

beforeEach(function (): void {
    $this->keyPair = SshKeyPair::generate();

    $this->signInsStarted = 0;

    Event::listen(function (KeyWritten $event): void {
        if (str_starts_with($event->key, 'device-authorization:')) {
            $this->signInsStarted++;
        }
    });
});

it('starts a sign-in for the key: a device code to poll with, and a user code to type on the web', function (): void {
    $response = signedApiRequest($this->keyPair, 'POST', route('api-device-authorization.store'), ['device_name' => 'devnull on a laptop']);

    $response->assertCreated();

    $deviceCode = $response->json('device_code');
    $userCode = $response->json('user_code');

    expect($deviceCode)->toMatch('/^[0-9a-f]{64}$/')
        ->and($userCode)->toMatch('/^[BCDFGHJKLMNPQRSTVWXZ]{4}-[BCDFGHJKLMNPQRSTVWXZ]{4}$/')
        ->and($response->json())->toBe([
            'device_code' => $deviceCode,
            'user_code' => $userCode,
            'verification_uri' => route('device-activation.show'),
            'verification_uri_complete' => route('device-activation.show', ['user_code' => $userCode]),
            'expires_in' => 900,
            'interval' => 5,
        ])
        ->and(Cache::get(DeviceAuthorization::keyFor($deviceCode)))->toBe([
            'fingerprint' => $this->keyPair->publicKey->fingerprint(),
            'device_name' => 'devnull on a laptop',
            'user_code' => $userCode,
            'expires_at' => now()->addMinutes(15)->getTimestamp(),
            'status' => 'pending',
            'user_id' => null,
        ])
        ->and(Cache::get(DeviceAuthorization::userCodeKeyFor($userCode)))->toBe(DeviceAuthorization::keyFor($deviceCode))
        ->and($this->signInsStarted)->toBe(1);

    Http::assertNothingSent();
});

it('never needs the key to be on github', function (): void {
    expect(SshKey::query()->exists())->toBeFalse();

    signedApiRequest($this->keyPair, 'POST', route('api-device-authorization.store'), ['device_name' => 'devnull on a laptop'])
        ->assertCreated();
});

it('validates the device name', function (array $data): void {
    signedApiRequest($this->keyPair, 'POST', route('api-device-authorization.store'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_name');

    expect($this->signInsStarted)->toBe(0);
})->with([
    'no name' => [['device_name' => '']],
    'a long name' => [['device_name' => str_repeat('a', 101)]],
    'a name that is not text' => [['device_name' => ['devnull on a laptop']]],
]);

it('rejects a request that is not signed right', function (Closure $server, string $message): void {
    $body = json_encode(['device_name' => 'devnull on a laptop'], JSON_THROW_ON_ERROR);

    $response = $this->call('POST', route('api-device-authorization.store'), server: $server($this->keyPair, $body), content: $body);

    $response->assertUnauthorized()
        ->assertExactJson(['message' => $message, 'code' => 'invalid_signature']);

    expect($this->signInsStarted)->toBe(0);
})->with([
    'no signature' => [fn (): array => ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], 'Malformed SSH signature.'],
    'a signature over another body' => [fn (SshKeyPair $keyPair): array => signApiRequest($keyPair, 'POST', route('api-device-authorization.store'), '{}'), 'Invalid signature.'],
    'an expired signature' => [fn (SshKeyPair $keyPair, string $body): array => signApiRequest($keyPair, 'POST', route('api-device-authorization.store'), $body, timestamp: now()->getTimestamp() - 301), 'Signature expired. Check that your clock is right.'],
]);

it('lets each key start 5 sign-ins a minute, wherever they come from', function (): void {
    $body = '{"device_name":"devnull on a laptop"}';

    foreach (range(1, 5) as $attempt) {
        $server = [...signApiRequest($this->keyPair, 'POST', route('api-device-authorization.store'), $body), 'REMOTE_ADDR' => "203.0.113.{$attempt}"];

        $this->call('POST', route('api-device-authorization.store'), server: $server, content: $body)->assertCreated();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-device-authorization.store'), ['device_name' => 'devnull on a laptop'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', 60)
        ->assertExactJson(['message' => 'Too Many Attempts.', 'code' => 'rate_limited']);

    signedApiRequest(SshKeyPair::generate(), 'POST', route('api-device-authorization.store'), ['device_name' => 'devnull on a laptop'])
        ->assertCreated();

    expect($this->signInsStarted)->toBe(6);
});

it('limits each address to 60 tries a minute, before checking signatures', function (): void {
    foreach (range(1, 60) as $attempt) {
        $this->postJson(route('api-device-authorization.store'))->assertUnauthorized();
    }

    $this->postJson(route('api-device-authorization.store'))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');
});
