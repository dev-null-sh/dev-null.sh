<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateUnclaimedProfileTopics;
use App\Models\Profile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Runs once a developer who isn't on /dev/null yet gets a page, or moves to a new handle, so the member waiting on
 * the lookup doesn't also wait for the commits that mentioned them to be found.
 */
#[DeleteWhenMissingModels]
final class LinkUnclaimedProfileTopics implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Profile $profile)
    {
        //
    }

    public function handle(CreateUnclaimedProfileTopics $action): void
    {
        $action->handle($this->profile);
    }
}
