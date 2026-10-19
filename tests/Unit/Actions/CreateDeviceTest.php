<?php

declare(strict_types=1);

use App\Actions\CreateDevice;
use App\Dto\AuthorizationCode;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * A code as the web handed it out, in the cache.
 */
function cachedAuthorizationCode(User $user, string $deviceName = 'devnull on iPhone'): AuthorizationCode
{
    $code = new AuthorizationCode(AuthorizationCode::keyFor(bin2hex(random_bytes(32))), $user->id, 'SHA256:key', $deviceName, 'challenge');

    Cache::put($code->key, $code->toArray(), now()->addMinutes(AuthorizationCode::EXPIRES_AFTER_MINUTES));

    return $code;
}

it('approves the key of an authorization code as a device of its user, and uses the code up', function (): void {
    $user = User::factory()->create();
    $code = cachedAuthorizationCode($user);

    $device = resolve(CreateDevice::class)->handle($code);

    expect($device?->refresh()->user_id)->toBe($user->id)
        ->and($device?->fingerprint)->toBe('SHA256:key')
        ->and($device?->name)->toBe('devnull on iPhone')
        ->and($device?->approved_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($device?->last_used_at)->toBeNull()
        ->and(Cache::has($code->key))->toBeFalse();
});

it('moves a key approved by someone else to the user who approved it now', function (): void {
    $user = User::factory()->create();
    $previous = Device::factory()->create(['fingerprint' => 'SHA256:key', 'last_used_at' => now()->subDay(), 'approved_at' => now()->subWeek()]);

    $device = resolve(CreateDevice::class)->handle(cachedAuthorizationCode($user, 'devnull on Pixel'));

    expect($device?->is($previous))->toBeTrue()
        ->and($device?->refresh()->user_id)->toBe($user->id)
        ->and($device?->name)->toBe('devnull on Pixel')
        ->and($device?->approved_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($device?->last_used_at)->toBeNull()
        ->and(Device::query()->count())->toBe(1);
});

it('approves nothing for a code that was redeemed since it was checked', function (): void {
    $user = User::factory()->create();
    $code = cachedAuthorizationCode($user);

    resolve(CreateDevice::class)->handle($code);
    Device::query()->delete();

    expect(resolve(CreateDevice::class)->handle($code))->toBeNull()
        ->and(Device::query()->exists())->toBeFalse();
});
