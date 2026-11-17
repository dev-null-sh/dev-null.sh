<?php

declare(strict_types=1);

use App\Dto\DeviceAuthorization;
use App\Enums\DeviceAuthorizationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;

it('keys a device code by its hash, never by the code itself', function (): void {
    expect(DeviceAuthorization::keyFor('the-device-code'))->toBe('device-authorization:'.hash('sha256', 'the-device-code'))
        ->not->toContain('the-device-code');
});

it('keys a user code by its hash, never by the code itself', function (): void {
    expect(DeviceAuthorization::userCodeKeyFor('BCDF-GHJK'))->toBe('device-authorization-user-code:'.hash('sha256', 'BCDF-GHJK'))
        ->not->toContain('BCDF-GHJK');
});

it('reads back what it stored, whether pending or decided', function (DeviceAuthorization $authorization): void {
    expect(DeviceAuthorization::fromArray($authorization->key, $authorization->toArray()))->toEqual($authorization);
})->with([
    'pending' => fn (): DeviceAuthorization => new DeviceAuthorization('device-authorization:key', 'SHA256:key', 'devnull on a laptop', 'BCDF-GHJK', CarbonImmutable::parse('2026-10-19 10:15:00')),
    'approved' => fn (): DeviceAuthorization => new DeviceAuthorization('device-authorization:key', 'SHA256:key', 'devnull on a laptop', 'BCDF-GHJK', CarbonImmutable::parse('2026-10-19 10:15:00'), DeviceAuthorizationStatus::Approved, 'user-id'),
    'denied' => fn (): DeviceAuthorization => new DeviceAuthorization('device-authorization:key', 'SHA256:key', 'devnull on a laptop', 'BCDF-GHJK', CarbonImmutable::parse('2026-10-19 10:15:00'), DeviceAuthorizationStatus::Denied),
]);

it('is approved for the user who approved it', function (): void {
    $user = User::factory()->create();
    $pending = new DeviceAuthorization('device-authorization:key', 'SHA256:key', 'devnull on a laptop', 'BCDF-GHJK', CarbonImmutable::parse('2026-10-19 10:15:00'));

    $approved = $pending->decided(DeviceAuthorizationStatus::Approved, $user);

    expect($approved->status)->toBe(DeviceAuthorizationStatus::Approved)
        ->and($approved->userId)->toBe($user->id)
        ->and($approved->key)->toBe($pending->key)
        ->and($approved->expiresAt)->toEqual($pending->expiresAt)
        ->and($pending->status)->toBe(DeviceAuthorizationStatus::Pending);
});

it('names nobody when it is denied', function (): void {
    $user = User::factory()->create();
    $pending = new DeviceAuthorization('device-authorization:key', 'SHA256:key', 'devnull on a laptop', 'BCDF-GHJK', CarbonImmutable::parse('2026-10-19 10:15:00'));

    $denied = $pending->decided(DeviceAuthorizationStatus::Denied, $user);

    expect($denied->status)->toBe(DeviceAuthorizationStatus::Denied)
        ->and($denied->userId)->toBeNull();
});
