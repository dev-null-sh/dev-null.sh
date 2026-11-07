<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\MessageReport;
use App\Models\UserReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The open reports on messages in remotes and on people, oldest first, without who filed them.
 */
final readonly class ReportedUsersQuery
{
    /** @return Collection<int, MessageReport> */
    public function messages(): Collection
    {
        return MessageReport::query()
            ->whereNull('dismissed_at')
            ->select(['id', 'message_id', 'reason', 'details', 'created_at'])
            ->with(['message' => fn (BelongsTo $query): BelongsTo => $query->select(['id', 'user_id', 'body', 'created_at'])->with('user')])
            ->oldest()->oldest('id')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, UserReport> */
    public function users(): Collection
    {
        return UserReport::query()
            ->whereNull('dismissed_at')
            ->select(['id', 'user_id', 'reason', 'details', 'created_at'])
            ->with('user')
            ->oldest()->oldest('id')
            ->limit(100)
            ->get();
    }
}
