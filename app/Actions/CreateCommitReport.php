<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;

final readonly class CreateCommitReport
{
    public function handle(User $user, Commit $commit, string $reason, ?string $details): void
    {
        CommitReport::query()->createOrFirst([
            'commit_id' => $commit->id,
            'reporter_id' => $user->id,
        ], [
            'reason' => $reason,
            'details' => $details,
        ]);
    }
}
