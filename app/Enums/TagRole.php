<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Maintainers run a tag: description, domain verification, and membership. Contributors are listed on the tag.
 */
enum TagRole: string
{
    case Maintainer = 'maintainer';
    case Contributor = 'contributor';
}
