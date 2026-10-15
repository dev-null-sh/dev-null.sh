<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Commit>
 */
final class CommitBuilder extends Builder
{
    /**
     * Approved commits are visible to everyone, while authors may also see their own pending or rejected commits.
     */
    public function visibleTo(User $user): self
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->where('status', CommitStatus::Approved)
            ->orWhere('user_id', $user->id));
    }

    public function pending(): self
    {
        return $this->where('status', CommitStatus::Pending);
    }

    public function approved(): self
    {
        return $this->where('status', CommitStatus::Approved);
    }
}
