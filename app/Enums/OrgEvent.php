<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a remote records in its history, shown as log lines between messages: membership changes, pings and calls.
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
    case Ping = 'ping';
    case CallStarted = 'call_started';
    case CallAnswered = 'call_answered';
    case CallCancelled = 'call_cancelled';
    case CallDeclined = 'call_declined';
    case CallMissed = 'call_missed';
    case CallFailed = 'call_failed';
}
