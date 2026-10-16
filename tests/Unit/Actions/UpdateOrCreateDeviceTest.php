<?php

declare(strict_types=1);

use App\Actions\UpdateOrCreateDevice;
use App\Models\Device;
use App\Models\User;

it('approves a key as a device of the user', function (): void {
    $user = User::factory()->create();

    $device = resolve(UpdateOrCreateDevice::class)->handle($user->id, 'SHA256:key', 'devnull on iPad');

    expect($device->user_id)->toBe($user->id)
        ->and($device->fingerprint)->toBe('SHA256:key')
        ->and($device->name)->toBe('devnull on iPad')
        ->and($device->approved_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('moves a key approved for someone else to the user', function (): void {
    $user = User::factory()->create();
    $previous = Device::factory()->create(['fingerprint' => 'SHA256:key', 'last_used_at' => now()]);

    $device = resolve(UpdateOrCreateDevice::class)->handle($user->id, 'SHA256:key', 'devnull on iPad');

    expect($device->is($previous))->toBeTrue()
        ->and($device->refresh()->user_id)->toBe($user->id)
        ->and($device->last_used_at)->toBeNull()
        ->and(Device::query()->count())->toBe(1);
});
