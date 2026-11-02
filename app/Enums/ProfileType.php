<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kind of GitHub account a profile mirrors, as GitHub's `ProfileOwner` is a user or an organization.
 */
enum ProfileType: string
{
    case User = 'user';
    case Organization = 'organization';
}
