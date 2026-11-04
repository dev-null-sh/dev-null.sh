<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A feature administrators can switch on or off for everyone from the operations dashboard.
 */
enum Feature: string
{
    case Translations = 'translations';
    case OrganizationCommits = 'organization_commits';
    case NowPlaying = 'now_playing';

    /**
     * Features are on until switched off, except one still being built, which stays off until switched on.
     */
    public function isEnabledByDefault(): bool
    {
        return ! in_array($this, [self::OrganizationCommits, self::NowPlaying], true);
    }
}
