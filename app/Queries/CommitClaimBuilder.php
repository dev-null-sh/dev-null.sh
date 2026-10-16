<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CommitClaim;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<CommitClaim>
 */
final class CommitClaimBuilder extends Builder
{
    public function active(): self
    {
        return $this->where($this->qualifyColumn('expires_at'), '>', now());
    }
}
