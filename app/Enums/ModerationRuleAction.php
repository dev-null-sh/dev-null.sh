<?php

declare(strict_types=1);

namespace App\Enums;

enum ModerationRuleAction: string
{
    case Reject = 'reject';
    case Review = 'review';
    case Approve = 'approve';
}
