<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lists of people on a profile: who follows them, who they follow, who of those follow them back, and which of
 * their followers the viewer follows too.
 */
enum ConnectionType: string
{
    case Followers = 'followers';
    case FollowersYouKnow = 'followers_you_know';
    case Following = 'following';
    case Mutuals = 'mutuals';
}
