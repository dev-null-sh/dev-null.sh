<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityGraphTheme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case Auto = 'auto';
}
