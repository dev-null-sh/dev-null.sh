<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Device;

final readonly class DeleteDevicePushToken
{
    /**
     * Nothing is pushed to the device until it sends a token again.
     */
    public function handle(Device $device): void
    {
        $device->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);
    }
}
