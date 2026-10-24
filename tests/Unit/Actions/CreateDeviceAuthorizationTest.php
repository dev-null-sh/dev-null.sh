<?php

declare(strict_types=1);

use App\Actions\CreateDeviceAuthorization;
use App\Dto\DeviceAuthorization;
use App\Enums\DeviceAuthorizationStatus;
use Illuminate\Support\Facades\Cache;

it('hands out a device code to poll with and a user code to type, bound to the key, for fifteen minutes', function (): void {
    ['device_code' => $deviceCode, 'authorization' => $authorization] = resolve(CreateDeviceAuthorization::class)->handle('SHA256:key', 'devnull on a laptop');

    expect($deviceCode)->toMatch('/^[0-9a-f]{64}$/')
        ->and($authorization->key)->toBe(DeviceAuthorization::keyFor($deviceCode))
        ->and($authorization->fingerprint)->toBe('SHA256:key')
        ->and($authorization->deviceName)->toBe('devnull on a laptop')
        ->and($authorization->userCode)->toMatch('/^[BCDFGHJKLMNPQRSTVWXZ]{4}-[BCDFGHJKLMNPQRSTVWXZ]{4}$/')
        ->and($authorization->status)->toBe(DeviceAuthorizationStatus::Pending)
        ->and($authorization->userId)->toBeNull()
        ->and($authorization->expiresAt->getTimestamp())->toBe(now()->addMinutes(15)->getTimestamp())
        ->and(Cache::get($authorization->key))->toBe([
            'fingerprint' => 'SHA256:key',
            'device_name' => 'devnull on a laptop',
            'user_code' => $authorization->userCode,
            'expires_at' => now()->addMinutes(15)->getTimestamp(),
            'status' => 'pending',
            'user_id' => null,
        ])
        ->and(Cache::get(DeviceAuthorization::userCodeKeyFor($authorization->userCode)))->toBe($authorization->key);

    $this->travel(DeviceAuthorization::EXPIRES_AFTER_MINUTES)->minutes();

    expect(Cache::has($authorization->key))->toBeFalse()
        ->and(Cache::has(DeviceAuthorization::userCodeKeyFor($authorization->userCode)))->toBeFalse();
});

it('hands out different codes every time', function (): void {
    $first = resolve(CreateDeviceAuthorization::class)->handle('SHA256:key', 'devnull on a laptop');
    $second = resolve(CreateDeviceAuthorization::class)->handle('SHA256:key', 'devnull on a laptop');

    expect($first['device_code'])->not->toBe($second['device_code'])
        ->and($first['authorization']->userCode)->not->toBe($second['authorization']->userCode)
        ->and(Cache::has($first['authorization']->key))->toBeTrue()
        ->and(Cache::has($second['authorization']->key))->toBeTrue();
});

it('picks another user code when the first one is already out', function (): void {
    $indexKeys = [];

    Cache::shouldReceive('add')
        ->twice()
        ->withArgs(function (string $key) use (&$indexKeys): bool {
            $indexKeys[] = $key;

            return true;
        })
        ->andReturn(false, true);
    Cache::shouldReceive('put')->once()->andReturnTrue();

    ['authorization' => $authorization] = resolve(CreateDeviceAuthorization::class)->handle('SHA256:key', 'devnull on a laptop');

    expect($indexKeys)->toHaveCount(2)
        ->and($indexKeys[0])->not->toBe($indexKeys[1])
        ->and($indexKeys[1])->toBe(DeviceAuthorization::userCodeKeyFor($authorization->userCode));
});
