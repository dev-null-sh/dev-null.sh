<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;

final readonly class ApproveCommits
{
    public function __construct(private ModerateCommits $moderateCommits)
    {
        //
    }

    /**
     * @param  array<int, string>  $commitIds
     */
    public function handle(array $commitIds): void
    {
        $this->moderateCommits->handle($commitIds, CommitStatus::Approved, onlyPending: true);
    }
}
