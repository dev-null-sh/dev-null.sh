<?php

declare(strict_types=1);

use App\Actions\CreateDeviceActivation;
use App\Dto\DeviceAuthorization;
use App\Enums\DeviceAuthorizationStatus;
use App\Models\User;
use App\Services\DeviceAuthorizations;
use Illuminate\Support\Facades\Cache;

it('finds the sign-in waiting for a decision behind a user code', function (): void {
    $authorization = pendingDeviceAuthorization()['authorization'];

    expect(new DeviceAuthorizations()->pendingFromUserCode($authorization->userCode))->toEqual($authorization);
});

it('finds nothing behind a code that was never handed out', function (): void {
    expect(new DeviceAuthorizations()->pendingFromUserCode('BCDF-GHJK'))->toBeNull();
});

it('finds nothing behind a code whose sign-in is gone', function (): void {
    Cache::put(DeviceAuthorization::userCodeKeyFor('BCDF-GHJK'), DeviceAuthorization::keyFor('gone'), now()->addMinute());

    expect(new DeviceAuthorizations()->pendingFromUserCode('BCDF-GHJK'))->toBeNull();
});

it('finds nothing behind a code once it is decided', function (DeviceAuthorizationStatus $decision): void {
    $authorization = pendingDeviceAuthorization()['authorization'];

    resolve(CreateDeviceActivation::class)->handle(User::factory()->create(), $authorization, $decision);

    expect(new DeviceAuthorizations()->pendingFromUserCode($authorization->userCode))->toBeNull();
})->with([
    'approved' => DeviceAuthorizationStatus::Approved,
    'denied' => DeviceAuthorizationStatus::Denied,
]);
