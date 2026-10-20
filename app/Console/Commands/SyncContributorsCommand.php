<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\UpdateRepositoryContributors;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('contributors:sync')]
#[Description('Award contributor badges from GitHub repository issues, pull requests, commits, and comments')]
final class SyncContributorsCommand extends Command
{
    public function handle(UpdateRepositoryContributors $action): int
    {
        try {
            $awarded = $action->handle();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            $this->components->error('Could not sync repository contributors from GitHub. Try again later.');

            return self::FAILURE;
        }

        $this->components->info("Awarded the contributor badge to {$awarded} users.");

        return self::SUCCESS;
    }
}
