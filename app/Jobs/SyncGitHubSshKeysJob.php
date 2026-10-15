<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncGitHubSshKeys;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

#[Backoff([10, 60])]
#[Tries(3)]
final class SyncGitHubSshKeysJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user)
    {
        //
    }

    public function handle(SyncGitHubSshKeys $syncGitHubSshKeys): void
    {
        $syncGitHubSshKeys->handle($this->user);
    }
}
