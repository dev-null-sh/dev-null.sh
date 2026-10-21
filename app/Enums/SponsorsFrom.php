<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who a maintainer's sponsors org takes in: monthly sponsors only, every active sponsor (one-time sponsors for thirty
 * days after they pay), or everyone who has ever sponsored, lapsed or not.
 */
enum SponsorsFrom: string
{
    case Monthly = 'monthly';
    case Active = 'active';
    case Everyone = 'everyone';
}
