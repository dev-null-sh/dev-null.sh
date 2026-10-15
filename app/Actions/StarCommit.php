<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class StarCommit
{
    public function __construct(private NotifyAboutStar $notifyAboutStar)
    {
        //
    }

    public function handle(User $user, Commit $commit): void
    {
        DB::transaction(function () use ($user, $commit): void {
            $user->starredCommits()->syncWithoutDetaching([$commit->id]);

            $this->notifyAboutStar->handle($user, $commit);
        });
    }
}
