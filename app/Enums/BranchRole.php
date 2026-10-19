<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Maintainers run a branch: domain verification, membership, and ownership transfer. Contributors can edit the description and are listed on the branch.
 */
enum BranchRole: string
{
    case Maintainer = 'maintainer';
    case Contributor = 'contributor';
}
