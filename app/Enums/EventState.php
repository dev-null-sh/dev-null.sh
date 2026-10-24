<?php

declare(strict_types=1);

namespace App\Enums;

enum EventState: string
{
    case Upcoming = 'upcoming';
    case Live = 'live';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}
