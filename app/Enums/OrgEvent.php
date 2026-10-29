<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a remote records in its history, shown as log lines between messages: an org's membership changes, who can ping
 * it, and pings.
 */
enum OrgEvent: string
{
    case Created = 'created';
    case Added = 'added';
    case Removed = 'removed';
    case Left = 'left';
    case Renamed = 'renamed';
    case Promoted = 'promoted';
    case Demoted = 'demoted';
    case Inherited = 'inherited';
    case PingsChanged = 'pings_changed';
    case Ping = 'ping';
}
