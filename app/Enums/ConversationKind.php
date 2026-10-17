<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationKind: string
{
    case Direct = 'direct';
    case Org = 'org';
}
