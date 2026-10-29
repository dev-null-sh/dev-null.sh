<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two replies of a merge conflict, named like the sides git asks you to pick between.
 */
enum MergeConflictSide: string
{
    case Ours = 'ours';
    case Theirs = 'theirs';
}
