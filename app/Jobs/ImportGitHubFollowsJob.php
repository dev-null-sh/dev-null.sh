<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateUserGitHubFollows;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([10, 60])]
#[Tries(3)]
final class ImportGitHubFollowsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user, public bool $notify = true)
    {
        //
    }

    public function handle(CreateUserGitHubFollows $createUserGitHubFollows): void
    {
        $createUserGitHubFollows->handle($this->user, $this->notify);
    }
}
