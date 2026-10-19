<?php

declare(strict_types=1);

use App\Actions\CreateDeviceActivation;
use App\Dto\DeviceAuthorization;
use App\Enums\DeviceAuthorizationStatus;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('approves the key as a device of the user, records it for the terminal to find, and takes the code back', function (): void {
    $user = User::factory()->create();
    $authorization = pendingDeviceAuthorization('SHA256:key', 'devnull on a laptop')['authorization'];

    resolve(CreateDeviceActivation::class)->handle($user, $authorization, DeviceAuthorizationStatus::Approved);

    $device = Device::query()->sole();

    expect($device->user_id)->toBe($user->id)
        ->and($device->fingerprint)->toBe('SHA256:key')
        ->and($device->name)->toBe('devnull on a laptop')
        ->and($device->approved_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($device->last_used_at)->toBeNull()
        ->and(Cache::get($authorization->key))->toBe([...$authorization->toArray(), 'status' => 'approved', 'user_id' => $user->id])
        ->and(Cache::has(DeviceAuthorization::userCodeKeyFor($authorization->userCode)))->toBeFalse();
});

it('keeps the decision only until the code would have expired', function (): void {
    $user = User::factory()->create();
    $authorization = pendingDeviceAuthorization()['authorization'];

    $this->travel(14)->minutes();

    resolve(CreateDeviceActivation::class)->handle($user, $authorization, DeviceAuthorizationStatus::Approved);

    $this->travel(59)->seconds();

    expect(Cache::has($authorization->key))->toBeTrue();

    $this->travel(1)->seconds();

    expect(Cache::has($authorization->key))->toBeFalse();
});

it('moves a key approved by someone else to the user who approved it now', function (): void {
    $user = User::factory()->create();
    $previous = Device::factory()->create(['fingerprint' => 'SHA256:key', 'last_used_at' => now()->subDay(), 'approved_at' => now()->subWeek()]);

    resolve(CreateDeviceActivation::class)->handle($user, pendingDeviceAuthorization('SHA256:key', 'devnull on a desktop')['authorization'], DeviceAuthorizationStatus::Approved);

    expect($previous->refresh()->user_id)->toBe($user->id)
        ->and($previous->name)->toBe('devnull on a desktop')
        ->and($previous->approved_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($previous->last_used_at)->toBeNull()
        ->and(Device::query()->count())->toBe(1);
});

it('denies without approving anything, and takes the code back', function (): void {
    $user = User::factory()->create();
    $authorization = pendingDeviceAuthorization()['authorization'];

    resolve(CreateDeviceActivation::class)->handle($user, $authorization, DeviceAuthorizationStatus::Denied);

    expect(Device::query()->exists())->toBeFalse()
        ->and(Cache::get($authorization->key))->toBe([...$authorization->toArray(), 'status' => 'denied', 'user_id' => null])
        ->and(Cache::has(DeviceAuthorization::userCodeKeyFor($authorization->userCode)))->toBeFalse();
});
