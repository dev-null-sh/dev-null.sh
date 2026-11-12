<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\LookUpGitHubProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final readonly class CreateProfileLookup
{
    /**
     * Queues the handle's lookup, which its page shows pending until the job is done or the page stops waiting.
     */
    public function handle(string $handle): void
    {
        $handle = Str::lower($handle);

        Cache::put("github-profiles:looking-up:{$handle}", true, LookUpGitHubProfile::WAIT_SECONDS);

        dispatch(new LookUpGitHubProfile($handle));
    }
}
