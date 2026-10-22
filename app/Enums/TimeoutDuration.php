<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How long a timeout lasts, in minutes.
 */
enum TimeoutDuration: int
{
    use HasOptions;

    case OneHour = 60;
    case OneDay = 1440;
    case ThreeDays = 4320;
    case SevenDays = 10080;
    case ThirtyDays = 43200;

    public function label(): string
    {
        return match ($this) {
            self::OneHour => '1 hour',
            self::OneDay => '1 day',
            self::ThreeDays => '3 days',
            self::SevenDays => '7 days',
            self::ThirtyDays => '30 days',
        };
    }
}
