<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Admins manage an organization's members on /dev/null: they add them, remove them, and make them admins or members.
 */
enum OrganizationRole: string
{
    case Admin = 'admin';
    case Member = 'member';
}
