<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\LookUpUserOrganizations;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:look-up-organizations')]
#[Description('Queue a lookup of the GitHub organizations the bio or company of every user mentions')]
final class LookUpUserOrganizationsCommand extends Command
{
    /**
     * Safe to run again: handles already known, or already found not to be an organization, cost no request.
     */
    public function handle(): int
    {
        $queued = User::query()
            ->lazyById()
            ->filter(LookUpUserOrganizations::dispatchFor(...))
            ->count();

        $this->components->info("Queued the organizations of {$queued} users to be looked up.");

        return self::SUCCESS;
    }
}
