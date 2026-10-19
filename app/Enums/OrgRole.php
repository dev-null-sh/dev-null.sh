<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Maintainers run the org: they rename it, add and remove people, and promote others. Contributors push and read.
 */
enum OrgRole: string
{
    case Maintainer = 'maintainer';
    case Contributor = 'contributor';
}
