<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a sign-in from a terminal stands: waiting for the user to decide on the web, or decided either way.
 */
enum DeviceAuthorizationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
