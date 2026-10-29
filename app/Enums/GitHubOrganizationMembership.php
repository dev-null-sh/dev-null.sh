<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What GitHub says about a user's place in an organization, when they ask with the read:org scope.
 */
enum GitHubOrganizationMembership
{
    /** An owner or a member, who accepted the invitation. */
    case Member;

    case NotMember;

    /** The organization restricts OAuth apps, and its owners haven't approved /dev/null. */
    case Restricted;

    /** The organization signs its members in with SAML, and the user hasn't signed in that way. */
    case SingleSignOn;
}
