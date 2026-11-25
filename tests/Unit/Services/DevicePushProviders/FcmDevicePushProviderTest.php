<?php

declare(strict_types=1);

use App\Dto\DevicePush;
use App\Dto\DevicePushResult;
use App\Dto\FcmCredentials;
use App\Exceptions\DevicePushCredentialsRejected;
use App\Models\Device;
use App\Models\Profile;
use App\Models\User;
use App\Services\DevicePushProviders\FcmDevicePushProvider;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->freezeTime();
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    $this->publicKey = openssl_pkey_get_details($key)['key'];

    config(['services.fcm.credentials' => [
        'type' => 'service_account',
        'project_id' => 'devnull-test',
        'private_key_id' => 'key-1',
        'private_key' => $privateKey,
        'client_email' => 'push@devnull-test.iam.gserviceaccount.com',
        'client_id' => '114789356916527470462',
        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
        'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/push%40devnull-test.iam.gserviceaccount.com',
        'universe_domain' => 'googleapis.com',
    ]]);

    $this->device = Device::factory()->withPushToken('fcm-phone')->create();
    $this->push = new DevicePush('Alice', 'Sent you a message', ['type' => 'message', 'conversation_id' => 'c1'], 300, 'c1');
});

function fakeFcm(int $status = 200, array $body = ['name' => 'projects/devnull-test/messages/1'], array $headers = []): void
{
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => Http::response($body, $status, $headers),
    ]);
}

function fcmAccessTokenCacheKey(): string
{
    $credentials = FcmCredentials::fromConfig();

    return 'fcm-access-token:'.hash('sha256', implode("\0", [
        $credentials->projectId, $credentials->clientEmail, $credentials->privateKey, $credentials->tokenUri,
    ]));
}

/**
 * @return array{Connection, Device}
 */
function committedPushDevice(): array
{
    $connection = DB::build([...DB::connection()->getConfig(), 'name' => 'fcm-concurrency']);
    $profile = Profile::factory()->make(['id' => (string) Str::uuid7()]);
    test()->beforeApplicationDestroyed(function () use ($connection, $profile): void {
        $connection->table('users')->where('id', $profile->id)->delete();
        $connection->table('profiles')->where('id', $profile->id)->delete();
        $connection->disconnect();
    });
    $connection->table('profiles')->insert($profile->getAttributes());
    $user = User::factory()->make(['id' => $profile->id, 'github_id' => $profile->github_id]);
    $device = Device::factory()->withPushToken('locked-phone')->make(['id' => (string) Str::uuid7(), 'user_id' => $user->id]);
    $connection->table('users')->insert($user->getAttributes());
    $connection->table('devices')->insert($device->getAttributes());

    return [$connection, Device::query()->findOrFail($device->id)];
}

it('sends who did something to the phone, signed with the service account', function (): void {
    fakeFcm();

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeFalse();

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://oauth2.googleapis.com/token') {
            return false;
        }

        $claims = JWT::decode($request['assertion'], new Key($this->publicKey, 'RS256'));

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && $claims->iss === 'push@devnull-test.iam.gserviceaccount.com'
            && $claims->scope === 'https://www.googleapis.com/auth/firebase.messaging'
            && $claims->aud === 'https://oauth2.googleapis.com/token';
    });
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/devnull-test/messages:send'
        && $request->header('Authorization') === ['Bearer access-1']
        && json_decode($request->body(), true)['message'] === [
            'token' => 'fcm-phone',
            'notification' => ['title' => 'Alice', 'body' => 'Sent you a message'],
            'data' => ['type' => 'message', 'conversation_id' => 'c1', 'url' => 'devnull://push?type=message&conversation_id=c1'],
            'android' => ['priority' => 'high', 'ttl' => '300s'],
            'apns' => ['headers' => ['apns-expiration' => (string) (now()->getTimestamp() + 300)], 'payload' => ['aps' => ['sound' => 'default', 'thread-id' => 'c1']]],
        ]);
});

it('leaves the thread out of a push that has no group', function (): void {
    fakeFcm();

    new FcmDevicePushProvider()->send($this->device, new DevicePush('Alice', 'Followed you', [], 86400));

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/')
        && json_decode($request->body(), true)['message']['apns'] === ['headers' => ['apns-expiration' => (string) (now()->getTimestamp() + 86400)], 'payload' => ['aps' => ['sound' => 'default']]]);
});

it('keeps the access token, encrypted, until shortly before it expires', function (): void {
    fakeFcm();
    $provider = new FcmDevicePushProvider();

    $provider->send($this->device, $this->push);
    expect(Cache::get(fcmAccessTokenCacheKey()))->not->toContain('access-1')
        ->and(Crypt::decryptString(Cache::get(fcmAccessTokenCacheKey())))->toBe('access-1');
    $this->travel(3500)->seconds();
    $provider->send($this->device, $this->push);
    Http::assertSentCount(3);

    $this->travel(40)->seconds();
    $provider->send($this->device, $this->push);
    Http::assertSentCount(5);
});

it('fetches a new access token when the cached one was encrypted under an older app key', function (): void {
    fakeFcm();
    Cache::put(fcmAccessTokenCacheKey(), 'not-decryptable-with-this-key', now()->addHour());

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeFalse()
        ->and(Crypt::decryptString(Cache::get(fcmAccessTokenCacheKey())))->toBe('access-1');

    Http::assertSentCount(2);
});

it('scopes access token reuse and invalidation to the credentials', function (Closure $change): void {
    $originalCredentials = config('services.fcm.credentials');
    Http::fake([
        'https://oauth2.googleapis.com/*' => Http::sequence()
            ->push(['access_token' => 'access-1', 'expires_in' => 3599])
            ->push(['access_token' => 'access-2', 'expires_in' => 3599])
            ->push(['access_token' => 'access-3', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => Http::sequence()->push()->push([], 401)->push()->push(),
    ]);
    $provider = new FcmDevicePushProvider();

    $provider->send($this->device, $this->push);
    $change();
    expect($provider->send($this->device, $this->push)->retry)->toBeTrue();
    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();
    config(['services.fcm.credentials' => $originalCredentials]);
    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();

    expect(Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/'))
        ->map(fn (array $record): array => $record[0]->header('Authorization'))->values()->all())
        ->toBe([['Bearer access-1'], ['Bearer access-2'], ['Bearer access-3'], ['Bearer access-1']]);
    Http::assertSentCount(7);
})->with([
    'project' => [function (): void {
        config(['services.fcm.credentials.project_id' => 'other-project']);
    }],
    'account' => [function (): void {
        config(['services.fcm.credentials.client_email' => 'other@devnull-test.iam.gserviceaccount.com']);
    }],
    'private key' => [function (): void {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        config(['services.fcm.credentials.private_key' => $privateKey]);
    }],
    'token endpoint' => [function (): void {
        config(['services.fcm.credentials.token_uri' => 'https://oauth2.googleapis.com/other-token']);
    }],
]);

it('blocks concurrent registration changes during sending but not OAuth', function (string $change): void {
    [$connection, $device] = committedPushDevice();
    $connection->statement("set lock_timeout = '50ms'");
    $blocked = false;
    Http::fake([
        'https://oauth2.googleapis.com/token' => function () use ($connection, $device): PromiseInterface {
            expect($connection->table('devices')->where('id', $device->id)->update(['name' => 'Changed during OAuth']))->toBe(1);

            return Http::response(['access_token' => 'access-1', 'expires_in' => 3599]);
        },
        'https://fcm.googleapis.com/*' => function () use ($connection, $device, $change, &$blocked): PromiseInterface {
            expect($connection->table('devices')->where('id', $device->id)->update(['last_used_at' => now()]))->toBe(1);

            try {
                match ($change) {
                    'clear token' => $connection->table('devices')->where('id', $device->id)->update(['push_token' => null, 'push_token_hash' => null, 'push_platform' => null]),
                    'replace token' => $connection->table('devices')->where('id', $device->id)->update(['push_token' => Crypt::encryptString('replacement'), 'push_token_hash' => hash('sha256', 'replacement')]),
                    'delete device' => $connection->table('devices')->where('id', $device->id)->delete(),
                };
            } catch (QueryException $queryException) {
                expect($queryException->getCode())->toBe('55P03');
                $blocked = true;
            }

            return Http::response();
        },
    ]);

    expect(new FcmDevicePushProvider()->send($device, $this->push)->retry)->toBeFalse();

    expect($blocked)->toBeTrue()
        ->and($device->fresh())->push_token->toBe('locked-phone')->name->toBe('Changed during OAuth')
        ->and($device->fresh()->last_used_at?->toDateTimeString())->toBe(now()->toDateTimeString());
    Http::assertSentCount(2);
})->with(['clear token', 'replace token', 'delete device']);

it('retries busy registrations without waiting or sending', function (string $busy): void {
    [$connection, $device] = committedPushDevice();
    fakeFcm();
    DB::statement("set local lock_timeout = '50ms'");
    $connection->beginTransaction();

    try {
        match ($busy) {
            'sending' => $connection->select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['device-push:'.$device->id]),
            'registration changing' => $connection->table('devices')->where('id', $device->id)->lockForUpdate()->first(),
        };

        expect(new FcmDevicePushProvider()->send($device, $this->push))
            ->retry->toBeTrue()->deferred->toBeTrue()->retryAfterSeconds->toBe(5);
    } finally {
        $connection->rollBack();
    }

    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/'));
})->with(['sending', 'registration changing']);

it('does not mistake other database errors for a busy registration', function (): void {
    fakeFcm();
    $device = Device::factory()->make(['id' => 'invalid-device-id']);

    expect(fn (): DevicePushResult => new FcmDevicePushProvider()->send($device, $this->push))->toThrow(QueryException::class);

    Http::assertSentCount(1);
});

it('forgets a token the phone no longer has', function (int $status, array $body): void {
    Exceptions::fake();
    fakeFcm($status, $body);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeFalse()
        ->and($this->device->fresh())
        ->push_token_hash->toBeNull()
        ->push_token->toBeNull()
        ->push_platform->toBeNull();

    Exceptions::assertNothingReported();
})->with([
    'not found' => [404, ['error' => ['status' => 'NOT_FOUND', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]]],
    'unregistered' => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]]],
]);

it('keeps a token the phone registered while the push was on its way', function (): void {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => function (): PromiseInterface {
            Device::query()->whereKey($this->device->id)->update(['push_token_hash' => hash('sha256', 'fcm-new')]);

            return Http::response(['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]], 404);
        },
    ]);

    new FcmDevicePushProvider()->send($this->device, $this->push);

    expect($this->device->fresh()->push_token_hash)->toBe(hash('sha256', 'fcm-new'));
    Http::assertSentCount(2);
});

it('asks to retry temporary failures, and keeps the token', function (int $status, bool $retry): void {
    fakeFcm($status, ['error' => ['status' => 'X']]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBe($retry)
        ->and($this->device->fresh()->push_token)->toBe('fcm-phone');
})->with([
    'a bad request' => [400, false],
    'a wrong project' => [404, false],
    'forbidden' => [403, false],
    'rate limited' => [429, true],
    'unavailable' => [503, true],
]);

it('waits as long as Google asks before trying the phone again', function (int $status, string $header, int $expected): void {
    fakeFcm($status, ['error' => ['status' => 'X']], ['Retry-After' => $header]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retryAfterSeconds)->toBe($expected);
})->with([
    'unavailable for two minutes' => [503, '120', 120],
    'unavailable for an hour' => [503, '3600', 3600],
    'rate limited without a wait' => [429, '', 60],
    'rate limited for less than a minute' => [429, '30', 60],
]);

it('waits as long as Google asks before asking for another access token', function (int $status, string $header, int $expected): void {
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response([], $status, ['Retry-After' => $header])]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retryAfterSeconds)->toBe($expected);
})->with([
    'unavailable for two minutes' => [503, '120', 120],
    'rate limited without a wait' => [429, '', 60],
]);

it('fetches a new access token after one is refused', function (): void {
    fakeFcm(401);
    $provider = new FcmDevicePushProvider();

    expect($provider->send($this->device, $this->push)->retry)->toBeTrue();
    $provider->send($this->device, $this->push);

    Http::assertSentCount(4);
});

it('does not let a late unauthorized response erase a newer token or cooldown', function (string|int $replacement): void {
    $sends = 0;
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => function () use ($replacement, &$sends): PromiseInterface {
            if (++$sends === 1) {
                Cache::put(fcmAccessTokenCacheKey(), is_int($replacement) ? now()->getTimestamp() + $replacement : Crypt::encryptString($replacement), 60);

                return Http::response([], 401);
            }

            return Http::response();
        },
    ]);
    $provider = new FcmDevicePushProvider();

    expect($provider->send($this->device, $this->push)->retry)->toBeTrue();
    expect($provider->send($this->device, $this->push)->retryAfterSeconds)->toBe(is_int($replacement) ? $replacement : null);

    expect(Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/'))
        ->map(fn (array $record): array => $record[0]->header('Authorization'))->values()->all())
        ->toBe(is_int($replacement) ? [['Bearer access-1']] : [['Bearer access-1'], ['Bearer access-2']]);
    Http::assertSentCount(is_int($replacement) ? 2 : 3);
})->with(['newer token' => 'access-2', 'cooldown' => 60]);

it('reports APNs credential errors without discarding the OAuth token or retrying', function (): void {
    Exceptions::fake();
    fakeFcm(401, ['error' => ['message' => 'private-response-content', 'details' => [['errorCode' => 'THIRD_PARTY_AUTH_ERROR']]]]);
    $provider = new FcmDevicePushProvider();

    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();
    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'FCM APNs credentials were rejected (THIRD_PARTY_AUTH_ERROR).');
    expect(Crypt::decryptString(Cache::get(fcmAccessTokenCacheKey())))->toBe('access-1');
    expect($this->device->fresh()->push_token)->toBe('fcm-phone');
    Http::assertSentCount(3);
});

it('retries competing refreshes instead of requesting another OAuth token', function (): void {
    $provider = new FcmDevicePushProvider();
    $exchanges = 0;
    Http::fake([
        'https://oauth2.googleapis.com/token' => function () use ($provider, &$exchanges): PromiseInterface {
            if (++$exchanges === 1) {
                expect($provider->send($this->device, $this->push)->retry)->toBeTrue();
            }

            return Http::response(['access_token' => 'access-1', 'expires_in' => 3599]);
        },
        'https://fcm.googleapis.com/*' => Http::response(),
    ]);

    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();
    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();

    Http::assertSentCount(3);
});

it('shares the OAuth cooldown until another exchange is allowed', function (int $status, string $header, int $seconds): void {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::sequence()
            ->push([], $status, ['Retry-After' => $header])
            ->push(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => Http::response(),
    ]);
    $provider = new FcmDevicePushProvider();

    expect($provider->send($this->device, $this->push)->retryAfterSeconds)->toBe($seconds);
    $this->travel(7)->seconds();
    expect($provider->send($this->device, $this->push))
        ->retryAfterSeconds->toBe($seconds - 7)->deferred->toBeTrue();
    Http::assertSentCount(1);

    $this->travel($seconds - 7)->seconds();
    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();

    Http::assertSentCount(3);
})->with([
    'default minimum' => [429, '', 60],
    'explicit delay' => [429, '120', 120],
    'service unavailable' => [503, '120', 120],
]);

it('uses cached tokens without competing with a credential lock', function (): void {
    fakeFcm();
    Cache::put(fcmAccessTokenCacheKey(), Crypt::encryptString('access-1'), 3600);
    $lock = Cache::lock(fcmAccessTokenCacheKey().':lock', 30);
    $lock->get();

    try {
        expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeFalse();
    } finally {
        $lock->release();
    }

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/'));
});

it('honors numeric string OAuth cooldowns without requesting a token', function (): void {
    fakeFcm();
    Cache::put(fcmAccessTokenCacheKey(), (string) (now()->getTimestamp() + 120), 120);
    $this->travel(7)->seconds();

    expect(new FcmDevicePushProvider()->send($this->device, $this->push))
        ->retryAfterSeconds->toBe(113)->deferred->toBeTrue();

    Http::assertNothingSent();
});

it('rechecks tokens and cooldowns installed by a competing refresh before exchanging', function (bool $cooldown): void {
    fakeFcm();
    $store = Cache::store();
    Cache::shouldReceive('get')->andReturnUsing(fn (string $key, mixed $default = null): mixed => $store->get($key, $default));
    Cache::shouldReceive('integer')->andReturnUsing(fn (string $key, int $default): int => $store->integer($key, $default));
    Cache::shouldReceive('lock')->once()->andReturnUsing(function (string $key, int $seconds) use ($store, $cooldown): Lock {
        $store->put(fcmAccessTokenCacheKey(), $cooldown ? now()->getTimestamp() + 60 : Crypt::encryptString('access-2'), 60);

        return $store->lock($key, $seconds);
    });

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->deferred)->toBe($cooldown);

    Http::assertSentCount($cooldown ? 0 : 1);
    Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token');
})->with(['refreshed token' => false, 'new cooldown' => true]);

it('waits for competing cache work before invalidating a rejected token', function (): void {
    $lock = Cache::lock(fcmAccessTokenCacheKey().':lock', 30);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => function () use ($lock): PromiseInterface {
            $lock->get();

            return Http::response([], 401);
        },
    ]);
    Sleep::whenFakingSleep(fn () => $lock->release());

    try {
        expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeTrue();
        expect(Cache::has(fcmAccessTokenCacheKey()))->toBeFalse();
    } finally {
        $lock->release();
    }
});

it('keeps an unauthorized send retryable when its cached token changed to an unreadable value', function (bool $cooldown): void {
    Exceptions::fake();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => function () use ($cooldown): PromiseInterface {
            Cache::put(fcmAccessTokenCacheKey(), $cooldown ? (string) (now()->getTimestamp() + 60) : 'unreadable-ciphertext', 60);

            return Http::response([], 401);
        },
    ]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeTrue();
    expect(Cache::has(fcmAccessTokenCacheKey()))->toBe($cooldown);
    Exceptions::assertNothingReported();
})->with(['rotated app key' => false, 'Redis cooldown' => true]);

it('shares FCM quota cooldowns only with the affected project or phone', function (array $details, bool $projectWide): void {
    $otherDevice = Device::factory()->withPushToken('other-phone')->create();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['details' => $details]], 429, ['Retry-After' => '120'])
            ->push()->push(),
    ]);
    $provider = new FcmDevicePushProvider();

    expect($provider->send($this->device, $this->push))
        ->retryAfterSeconds->toBe(120)->deferred->toBeFalse();
    $this->travel(7)->seconds();
    expect($provider->send($this->device, $this->push))
        ->retryAfterSeconds->toBe(113)->deferred->toBeTrue();
    expect($provider->send($otherDevice, $this->push)->deferred)->toBe($projectWide);
    $this->travel(113)->seconds();
    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();

    Http::assertSentCount($projectWide ? 3 : 4);
})->with([
    'unspecified quota' => [[], true],
    'project quota' => [[['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['subject' => 'projects/123/messages']]]], true],
    'device quota' => [[['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['subject' => 'projects/123/registrations/fcm-phone']]]], false],
    'both quotas' => [[['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['subject' => 'projects/123/registrations/fcm-phone'], ['subject' => 'projects/123/messages']]]], true],
]);

it('does not shorten a quota cooldown when an earlier request finishes late', function (): void {
    $otherDevice = Device::factory()->withPushToken('other-phone')->create();
    $provider = new FcmDevicePushProvider();
    $sends = 0;
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => function () use ($provider, $otherDevice, &$sends): PromiseInterface {
            if (++$sends === 1) {
                $provider->send($otherDevice, $this->push);

                return Http::response([], 429, ['Retry-After' => '60']);
            }

            return Http::response([], 429, ['Retry-After' => '120']);
        },
    ]);
    $provider->send($this->device, $this->push);

    expect($provider->send($this->device, $this->push)->retryAfterSeconds)->toBe(120);
    Http::assertSentCount(3);
});

it('does not apply one projects quota cooldown to a different project', function (): void {
    fakeFcm(429);
    $provider = new FcmDevicePushProvider();
    $provider->send($this->device, $this->push);
    config(['services.fcm.credentials.project_id' => 'other-project']);

    expect($provider->send($this->device, $this->push)->deferred)->toBeFalse();

    Http::assertSentCount(4);
});

it('asks to retry when Firebase cannot be reached', function (): void {
    Http::fake(fn (): never => throw new ConnectionException('timed out'));

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeTrue();
});

it('releases the registration lock and retries when the send connection fails', function (): void {
    $transactionLevel = DB::transactionLevel();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => fn (): never => throw new ConnectionException('timed out'),
    ]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeTrue();

    expect(DB::transactionLevel())->toBe($transactionLevel);
    expect($this->device->fresh()->push_token)->toBe('fcm-phone');
});

it('rejects malformed signing credentials and throttles sanitized reports', function (): void {
    config(['services.fcm.credentials.private_key' => 'private-invalid-signing-key']);
    Exceptions::fake();
    Http::fake();
    $provider = new FcmDevicePushProvider();

    expect(fn (): DevicePushResult => $provider->send($this->device, $this->push))->toThrow(DevicePushCredentialsRejected::class, 'FCM OAuth credentials could not sign a request.');
    expect(fn (): DevicePushResult => $provider->send($this->device, $this->push))->toThrow(DevicePushCredentialsRejected::class, 'FCM OAuth credentials could not sign a request.');

    Exceptions::assertReported(fn (DevicePushCredentialsRejected $exception): bool => $exception->getMessage() === 'FCM OAuth credentials could not sign a request.' && ! $exception->getPrevious() instanceof Throwable);
    Exceptions::assertReportedCount(1);
    Http::assertNothingSent();
});

it('does not mistake a cache failure for a permanent signing failure', function (): void {
    Http::fake();
    $lock = Cache::lock(fcmAccessTokenCacheKey().':lock', 30);
    Cache::shouldReceive('lock')->andReturn($lock);
    Cache::shouldReceive('get')->once()->andThrow(new DomainException('cache unavailable'));

    expect(fn (): DevicePushResult => new FcmDevicePushProvider()->send($this->device, $this->push))
        ->toThrow(DomainException::class, 'cache unavailable');

    Http::assertNothingSent();
});

it('rejects refused credentials and throttles sanitized reports', function (int $status): void {
    Exceptions::fake();
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'private-response-content'], $status)]);

    $provider = new FcmDevicePushProvider();
    expect(fn (): DevicePushResult => $provider->send($this->device, $this->push))->toThrow(DevicePushCredentialsRejected::class, "FCM OAuth request failed (HTTP {$status}).");
    expect(fn (): DevicePushResult => $provider->send($this->device, $this->push))->toThrow(DevicePushCredentialsRejected::class, "FCM OAuth request failed (HTTP {$status}).");

    Exceptions::assertReported(fn (DevicePushCredentialsRejected $exception): bool => $exception->getMessage() === "FCM OAuth request failed (HTTP {$status})." && ! $exception->getPrevious() instanceof Throwable);
    Exceptions::assertReportedCount(1);

    $this->travel(5)->minutes();
    expect(fn (): DevicePushResult => $provider->send($this->device, $this->push))->toThrow(DevicePushCredentialsRejected::class, "FCM OAuth request failed (HTTP {$status}).");

    Exceptions::assertReportedCount(2);
    Http::assertSentCount(3);
})->with([400, 401, 403]);

it('settles unexpected Firebase client errors and throttles sanitized reports', function (int $status): void {
    Exceptions::fake();
    fakeFcm($status, ['error' => ['message' => 'private-response-content']]);
    $provider = new FcmDevicePushProvider();

    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();
    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === "FCM send request failed (HTTP {$status})." && ! $exception->getPrevious() instanceof Throwable);
    Exceptions::assertReportedCount(1);
    Http::assertSentCount(3);
})->with([400, 403, 404]);

dataset('changed push registrations', [
    'deleted device' => [function (Device $device): void {
        $device->delete();
    }],
    'cleared token' => [function (Device $device): void {
        $device->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);
    }],
    'missing encrypted token' => [function (Device $device): void {
        $device->update(['push_token' => null]);
    }],
    'replaced token' => [function (Device $device): void {
        $device->update(['push_token_hash' => hash('sha256', 'replacement'), 'push_token' => 'replacement']);
    }],
    'different owner' => [function (Device $device): void {
        $device->update(['user_id' => User::factory()->create()->id]);
    }],
]);

it('does not send from a stale device snapshot', function (Closure $change): void {
    fakeFcm();
    $change($this->device->fresh());

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeFalse();

    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/'));
})->with('changed push registrations');

it('rechecks registrations after the OAuth request', function (Closure $change): void {
    Http::fake([
        'https://oauth2.googleapis.com/token' => function () use ($change): PromiseInterface {
            $change($this->device->fresh());

            return Http::response(['access_token' => 'access-1', 'expires_in' => 3599]);
        },
        'https://fcm.googleapis.com/*' => Http::response(),
    ]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeFalse();

    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/'));
})->with('changed push registrations');

it('settles a corrupt encrypted phone token without preventing another phone from receiving its push', function (): void {
    fakeFcm();
    Exceptions::fake();
    Device::query()->whereKey($this->device->id)->update(['push_token' => 'corrupt-ciphertext']);
    $otherDevice = Device::factory()->withPushToken('other-phone')->create();
    $provider = new FcmDevicePushProvider();

    expect($provider->send($this->device, $this->push)->retry)->toBeFalse();
    expect($provider->send($otherDevice, $this->push)->retry)->toBeFalse();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/') && $request['message']['token'] === 'other-phone');
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'FCM device token could not be decrypted.');
});

it('asks to retry when Google cannot hand out an access token right now', function (int $status): void {
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response([], $status)]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeTrue();

    Http::assertSentCount(1);
})->with([429, 503]);

it('pushes nothing without a service account', function (?array $credentials): void {
    config(['services.fcm.credentials' => $credentials]);
    Http::fake();

    expect(new FcmDevicePushProvider()->send($this->device, $this->push)->retry)->toBeFalse();

    Http::assertNothingSent();
})->with([
    'no credentials' => [null],
    'an empty array' => [[]],
    'a missing project' => [['client_email' => 'push@devnull-test.iam.gserviceaccount.com', 'private_key' => 'key', 'token_uri' => 'https://oauth2.googleapis.com/token']],
    'a missing private key' => [['project_id' => 'devnull-test', 'client_email' => 'push@devnull-test.iam.gserviceaccount.com', 'token_uri' => 'https://oauth2.googleapis.com/token']],
]);
