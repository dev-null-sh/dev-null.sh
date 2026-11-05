<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Device;

final readonly class DeleteDevicePushToken
{
    /**
     * Nothing is pushed to the device until it sends a token again. This writes straight to the row rather than
     * through the copy authentication loaded, which can be out of date if a token arrived since.
     */
    public function handle(Device $device): void
    {
        Device::query()->whereKey($device->id)->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);
    }
}
