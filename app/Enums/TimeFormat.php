<?php

declare(strict_types=1);

namespace App\Enums;

enum TimeFormat: string
{
    case TwelveHour = '12h';
    case TwentyFourHour = '24h';
}
