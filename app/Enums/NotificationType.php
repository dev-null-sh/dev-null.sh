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
}
