<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Dto\DevicePush;
use App\Models\Device;

/**
 * Shows a notification on a phone, through the token the app registered for it.
 */
interface DevicePushProvider
{
    /**
     * Pushes to the phone, and forgets its token once the phone no longer has it. False when it should be tried again
     * later.
     */
    public function send(Device $device, DevicePush $push): bool;
}
