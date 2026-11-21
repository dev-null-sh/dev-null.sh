<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Conversation;
use RuntimeException;

/**
 * Maintainers added people at the same moment, and this batch no longer fits, as the request would have said.
 */
final class OrgIsFull extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('An org holds up to '.Conversation::MAX_MEMBERS.' people.');
    }
}
