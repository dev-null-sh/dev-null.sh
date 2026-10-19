<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Query\Builder;

enum ActivityMetric: string
{
    case Commits = 'commits';
    case Replies = 'replies';
    case Signups = 'signups';

    /**
     * The rows the metric counts by their `created_at`: top-level commits and replies whatever their status since,
     * deleted ones included, like the rest of the operations page, and users for signups.
     */
    public function rows(): Builder
    {
        return match ($this) {
            self::Commits => Commit::withTrashed()->whereNull('parent_id')->toBase(),
            self::Replies => Commit::withTrashed()->whereNotNull('parent_id')->toBase(),
            self::Signups => User::query()->toBase(),
        };
    }
}
