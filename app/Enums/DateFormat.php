<?php

declare(strict_types=1);

namespace App\Enums;

enum DateFormat: string
{
    case MonthFirst = 'month_first';
    case DayFirst = 'day_first';
}
