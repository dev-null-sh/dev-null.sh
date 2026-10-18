<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Everyone in a repo contributes to it. Maintainers also run it: they add and remove contributors, promote others, and
 * delete any commit in it.
 */
enum RepoRole: string
{
    case Maintainer = 'maintainer';
    case Contributor = 'contributor';
}
