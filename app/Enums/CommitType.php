<?php

declare(strict_types=1);

namespace App\Enums;

enum CommitType: string
{
    case Standard = 'standard';
    case Launch = 'launch';
}
