<?php

declare(strict_types=1);

namespace App\Enums;

enum EmbedKind: string
{
    case Video = 'video';
    case Audio = 'audio';
    case Code = 'code';
}
