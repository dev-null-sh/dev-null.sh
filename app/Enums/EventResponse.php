<?php

declare(strict_types=1);

namespace App\Enums;

enum EventResponse: string
{
    case Going = 'going';
    case Interested = 'interested';
}
