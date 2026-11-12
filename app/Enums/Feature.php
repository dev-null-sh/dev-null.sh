<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A feature administrators can switch on or off for everyone from the operations dashboard.
 */
enum Feature: string
{
    case Translations = 'translations';
}
