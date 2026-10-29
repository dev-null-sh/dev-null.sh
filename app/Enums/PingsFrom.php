<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who can ping an org. Its maintainers choose, and everyone in it can ping until they do.
 */
enum PingsFrom: string
{
    case Everyone = 'everyone';
    case Maintainers = 'maintainers';
    case Nobody = 'nobody';

    public function allows(OrgRole $role): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::Maintainers => $role === OrgRole::Maintainer,
            self::Nobody => false,
        };
    }
}
