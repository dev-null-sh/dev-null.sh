<?php

declare(strict_types=1);

namespace App\Enums;

enum CommitSource: string
{
    case Web = 'web';
    case Cli = 'cli';
    case Tui = 'tui';
    case Ios = 'ios';
    case Android = 'android';
    case Macos = 'macos';
    case Windows = 'windows';
    case Linux = 'linux';
}
