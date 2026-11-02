<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an entry of the operations event log comes from. Each one is read from the table that already records it.
 */
enum OperationsEventSource: string
{
    case Signups = 'signups';
    case Jobs = 'jobs';
    case Reports = 'reports';
    case Moderation = 'moderation';
    case Bans = 'bans';
    case Timeouts = 'timeouts';
    case Features = 'features';
}
