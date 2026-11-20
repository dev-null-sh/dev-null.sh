<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Dto\DevicePush;
use App\Dto\DevicePushResult;
use App\Models\Device;

/**
 * Shows a notification on a phone, through the token the app registered for it.
 */
interface DevicePushProvider
{
    /**
     * Pushes to the phone, and forgets its token once the phone no longer has it. The result says whether it should be
     * tried again later, and how long the service asked us to wait first.
     */
    public function send(Device $device, DevicePush $push): DevicePushResult;
}
