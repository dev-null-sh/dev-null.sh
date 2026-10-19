<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The membership changes an org records in its history, shown as log lines between messages.
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
}
