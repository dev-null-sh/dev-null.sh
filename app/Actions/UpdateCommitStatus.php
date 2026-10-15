<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;

final readonly class UpdateCommitStatus
{
    public function __construct(private UpdateCommitStatuses $updateCommitStatuses)
    {
        //
    }

    public function handle(Commit $commit, CommitStatus $status, ?string $reason = null): void
    {
        $this->updateCommitStatuses->handle([$commit->id], $status, $reason);
    }
}
