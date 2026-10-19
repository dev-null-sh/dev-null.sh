<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Maintainers run a branch: description, domain verification, and membership. Contributors are listed on the branch.
 */
enum BranchRole: string
{
    case Maintainer = 'maintainer';
    case Contributor = 'contributor';
}
