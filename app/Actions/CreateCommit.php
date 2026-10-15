<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommit
{
    public function __construct(private NotifyAboutReplies $notifyAboutReplies)
    {
        //
    }

    public function handle(User $user, string $body, ?Commit $parent = null): Commit
    {
        return DB::transaction(function () use ($user, $body, $parent): Commit {
            $commit = $user->commits()->create([
                'body' => $body,
                'parent_id' => $parent?->id,
                'status' => $user->isAdmin() ? CommitStatus::Approved : CommitStatus::Pending,
            ]);

            $this->notifyAboutReplies->handle($commit);

            return $commit;
        });
    }
}
