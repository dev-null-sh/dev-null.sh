<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case Followed = 'followed';
    case Replied = 'replied';
    case Mentioned = 'mentioned';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Quoted = 'quoted';
    case Starred = 'starred';
    case LeveledUp = 'leveled_up';
    case FirstCommit = 'first_commit';
    case TimedOut = 'timed_out';
    case StreakShieldEarned = 'streak_shield_earned';
    case StreakShieldUsed = 'streak_shield_used';
    case StreakEnded = 'streak_ended';
}
