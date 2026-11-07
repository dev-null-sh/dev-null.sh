<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommitRecommit
{
    public function __construct(private CreateCommit $createCommit)
    {
        //
    }

    public function handle(User $user, Commit $commit): Commit
    {
        return DB::transaction(function () use ($user, $commit): Commit {
            // Every recommit of this source locks the same row through the duplicate check and insert.
            $commit = Commit::query()->lockForUpdate()->findOrFail($commit->id);

            if ($commit->status !== CommitStatus::Approved || $commit->isOwnedBy($user)) {
                throw new AuthorizationException;
            }

            $alreadyRecommitted = Commit::query()
                ->where('user_id', $user->id)
                ->where('quoted_commit_id', $commit->id)
                ->where('body', '')
                ->exists();

            if ($alreadyRecommitted) {
                throw new AuthorizationException;
            }

            return $this->createCommit->handle($user, '', quotedCommit: $commit, linkPreview: false);
        }, attempts: 3);
    }
}
