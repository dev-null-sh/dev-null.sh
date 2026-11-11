<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateUnclaimedProfileTopics;
use App\Models\Commit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('commits:link-developers')]
#[Description('Link every commit to the developers it mentions who have a page but are not on /dev/null yet')]
final class LinkCommitDevelopersCommand extends Command
{
    /**
     * Reads the commits once, whatever the number of developers, and asks GitHub nothing. Safe to run again: the links
     * already made stay.
     */
    public function handle(CreateUnclaimedProfileTopics $createUnclaimedProfileTopics): int
    {
        $developers = $createUnclaimedProfileTopics->developers();

        $linked = $developers === [] ? 0 : $createUnclaimedProfileTopics->link(
            Commit::withTrashed()->where('body', 'like', '%@%')->select(['id', 'body', 'mentions'])->lazyById(500),
            $developers,
        );

        $this->components->info("Linked {$linked} commits to the developers they mention.");

        return self::SUCCESS;
    }
}
