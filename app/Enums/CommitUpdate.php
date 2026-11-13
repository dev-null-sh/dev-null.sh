<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What changed about a public commit, broadcast on its public channel. Only commits everyone could see are
 * broadcast, and only their ids: whoever listens reads them again with their own visibility.
 */
enum CommitUpdate: string
{
    case Engagement = 'engagement';
    case Poll = 'poll';
    case Preview = 'preview';
    case Translation = 'translation';
    case Visibility = 'visibility';
}
