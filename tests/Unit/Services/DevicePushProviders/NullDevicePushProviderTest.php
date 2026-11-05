<?php

declare(strict_types=1);

use App\Dto\DevicePush;
use App\Models\Device;
use App\Services\DevicePushProviders\NullDevicePushProvider;
use Illuminate\Support\Facades\Http;

it('pushes nothing anywhere', function (): void {
    Http::fake();

    expect(new NullDevicePushProvider()->send(Device::factory()->withPushToken()->create(), new DevicePush('Alice', 'Followed you', [])))->toBeTrue();

    Http::assertNothingSent();
});
