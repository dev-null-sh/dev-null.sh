<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ImportAllGitHubFollowsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('follows:import')]
#[Description('Queue the import of who and which organizations every user not imported yet follows, and who follows them, on GitHub, without notifying anyone')]
final class ImportGitHubFollowsCommand extends Command
{
    public function handle(): int
    {
        dispatch(new ImportAllGitHubFollowsJob());

        $this->components->info('Queued the import, '.ImportAllGitHubFollowsJob::BatchSize.' users at a time. It waits for the GitHub rate limit to reset by itself, keeping '.ImportAllGitHubFollowsJob::Reserve.' requests for the rest of the app, and logs when every user is imported.');

        return self::SUCCESS;
    }
}
