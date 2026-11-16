<?php

declare(strict_types=1);

namespace App\Services\DevicePushProviders;

use App\Contracts\Services\DevicePushProvider;
use App\Dto\DevicePush;
use App\Dto\DevicePushResult;
use App\Models\Device;

final readonly class NullDevicePushProvider implements DevicePushProvider
{
    public function send(Device $device, DevicePush $push): DevicePushResult
    {
        return DevicePushResult::settled();
    }
}
