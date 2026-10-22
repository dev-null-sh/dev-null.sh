<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who someone is in a broadcast. The owner runs it and, with the publishers they pick, is the only one who can post;
 * subscribers read.
 */
enum BroadcastRole: string
{
    case Owner = 'owner';
    case Publisher = 'publisher';
    case Subscriber = 'subscriber';
}
