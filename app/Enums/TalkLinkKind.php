<?php

declare(strict_types=1);

namespace App\Enums;

enum TalkLinkKind: string
{
    case Slides = 'slides';
    case Recording = 'recording';
}
