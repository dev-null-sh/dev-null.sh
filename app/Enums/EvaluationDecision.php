<?php

declare(strict_types=1);

namespace App\Enums;

enum EvaluationDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Review = 'review';
    case Failed = 'failed';
}
