<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Making the member a contributor would leave the org without a maintainer. The request checks it first, so this only
 * happens when two maintainers step down, or make each other contributors, at the same moment.
 */
final class OrgNeedsMaintainer extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('An org needs at least one maintainer.');
    }
}
