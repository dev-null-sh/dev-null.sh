<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Organization;
use RuntimeException;

/**
 * Changing or removing the member would leave the organization without an admin. The request checks it first, so this
 * only happens when two admins change or remove each other at the same moment.
 */
final class OrganizationMustHaveAnAdmin extends RuntimeException
{
    public function __construct(public readonly Organization $organization)
    {
        parent::__construct(__('@:handle needs at least one admin.', ['handle' => $organization->handle]));
    }
}
