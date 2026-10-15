<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UnstarCommit
{
    public function handle(User $user, Commit $commit): void
    {
        DB::transaction(function () use ($user, $commit): void {
            $user->starredCommits()->detach($commit->id);

            Notification::query()
                ->where('type', NotificationType::Starred)
                ->where('actor_id', $user->id)
                ->where('commit_id', $commit->id)
                ->delete();
        });
    }
}
