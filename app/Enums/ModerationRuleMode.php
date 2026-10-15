<?php

declare(strict_types=1);

namespace App\Enums;

enum ModerationRuleMode: string
{
    case Off = 'off';
    case Test = 'test';
    case Live = 'live';
}
