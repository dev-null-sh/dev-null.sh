<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateTomChangelogCommits;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Tom posts about the commits that landed on production. A failed run isn't retried: the next scheduled one picks up
 * where it stopped.
 */
#[Timeout(900)]
#[Tries(1)]
#[UniqueFor(900)]
final class AnnounceProductionCommits implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(CreateTomChangelogCommits $action): void
    {
        $action->handle();
    }
}
