<?php

declare(strict_types=1);

namespace App\Enums;

enum ContributorRequestStatus: string
{
    case Open = 'open';
    case Filled = 'filled';
}
