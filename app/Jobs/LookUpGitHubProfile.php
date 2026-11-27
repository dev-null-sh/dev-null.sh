<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateGitHubProfile;
use App\Queries\GitHubLookupPauseQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Cache;

/**
 * Looks a handle up on GitHub while a member waits on its page, once: the page gives up after half a minute, and its
 * member tries again from there. It waits for nothing, so a paused lookup leaves the page saying until when.
 */
#[Tries(1)]
#[UniqueFor(60)]
final class LookUpGitHubProfile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * How long a page waits for its lookup, as long as it shows it pending.
     */
    public const int WAIT_SECONDS = 30;

    /**
     * @param  string  $handle  Lowercase, like the cache keys it shares with the page.
     */
    public function __construct(public readonly string $handle)
    {
        //
    }

    public function uniqueId(): string
    {
        return $this->handle;
    }

    public function handle(GitHubLookupPauseQuery $pause, CreateGitHubProfile $action): void
    {
        try {
            if (! $pause->get() instanceof CarbonImmutable) {
                $action->handle($this->handle);
            }
        } finally {
            Cache::forget("github-profiles:looking-up:{$this->handle}");
        }
    }
}
