<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([10, 60])]
#[Tries(3)]
final class AwardContributorBadgeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $githubId)
    {
        //
    }

    public function handle(): void
    {
        User::query()
            ->where('github_id', $this->githubId)
            ->where('is_contributor', false)
            ->update(['is_contributor' => true]);
    }
}
