<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectionType: string
{
    case Followers = 'followers';
    case FollowersYouKnow = 'followers_you_know';
    case Following = 'following';
    case Mutuals = 'mutuals';
}
