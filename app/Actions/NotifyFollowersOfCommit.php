<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Notifications\NewCommitFromFollowing;
use Illuminate\Support\Facades\Notification;

final readonly class NotifyFollowersOfCommit
{
    public function handle(Commit $commit): void
    {
        Notification::send($commit->user->followers()->get(), new NewCommitFromFollowing($commit));
    }
}
