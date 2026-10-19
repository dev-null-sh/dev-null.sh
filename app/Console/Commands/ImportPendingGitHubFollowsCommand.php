<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ImportPendingGitHubFollowsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('follows:pending')]
#[Description('Remember, in the background, for every user whose GitHub follows were imported, the accounts they follow on GitHub that are not on /dev/null yet')]
final class ImportPendingGitHubFollowsCommand extends Command
{
    public function handle(): int
    {
        dispatch(new ImportPendingGitHubFollowsJob);

        $this->components->info('Queued. The pending GitHub follows are remembered in the background, leaving GitHub requests to the rest of the app.');

        return self::SUCCESS;
    }
}
