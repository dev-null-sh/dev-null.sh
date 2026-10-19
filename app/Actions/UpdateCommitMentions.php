<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\PendingMention;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpdateCommitMentions
{
    /**
     * Turns the mentions of the user's username made before they signed in into mentions of them, which link to their
     * profile like any other, a few at a time, since a well-known developer may have been mentioned a lot. Each commit
     * is read again under a lock, so two developers mentioned together and signing in at once both keep theirs, and
     * keeps its update time, since nobody edited it.
     */
    public function handle(User $user, string $username): void
    {
        $username = Str::lower($username);

        PendingMention::query()
            ->where('username', $username)
            ->chunkById(200, function (Collection $pending) use ($username, $user): void {
                DB::transaction(function () use ($pending, $username, $user): void {
                    $commits = Commit::query()->withTrashed()->whereKey($pending->pluck('commit_id'))->lockForUpdate()->get();

                    foreach ($commits as $commit) {
                        $commit->timestamps = false;

                        // array_replace keeps the keys of usernames that are numbers, which unpacking would renumber...
                        $commit->update(['mentions' => array_replace($commit->mentions ?? [], [$username => $user->id])]);
                    }

                    PendingMention::query()->whereKey($pending->modelKeys())->delete();
                });
            });
    }
}
