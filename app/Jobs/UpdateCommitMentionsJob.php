<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitMentions;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
final class UpdateCommitMentionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * The username is the one the user signed in with, which a later rename must not change.
     */
    public function __construct(public User $user, public string $username)
    {
        //
    }

    public function handle(UpdateCommitMentions $updateCommitMentions): void
    {
        $updateCommitMentions->handle($this->user, $this->username);
    }
}
