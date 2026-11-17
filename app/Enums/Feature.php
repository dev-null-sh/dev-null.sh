<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A feature administrators can turn off, preview as admins, or enable for everyone.
 */
enum Feature: string
{
    case Translations = 'translations';
    case Guilds = 'guilds';
}
