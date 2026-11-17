<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of phone a push token is for, which decides how a push to it is shaped.
 */
enum PushPlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
}
