<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a broadcast is listed in the directory and on its owner's profile, open for anyone to subscribe to, or
 * hidden from everyone but its members and the people its owner invites.
 */
enum BroadcastVisibility: string
{
    case Public = 'public';
    case Invite = 'invite';
}
