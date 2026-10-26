<?php

declare(strict_types=1);

namespace App\Enums;

enum CommitSource: string
{
    case Cli = 'cli';
    case Ci = 'ci';
}
