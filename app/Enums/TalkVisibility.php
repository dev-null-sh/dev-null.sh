<?php

declare(strict_types=1);

namespace App\Enums;

enum TalkVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
}
