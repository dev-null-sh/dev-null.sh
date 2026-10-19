<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommitRestoration
{
    public function __construct(private UpdateCommitHotScores $updateCommitHotScores)
    {
        //
    }

    public function handle(Commit $commit): Commit
    {
        return DB::transaction(function () use ($commit): Commit {
            $commit = Commit::withTrashed()->lockForUpdate()->findOrFail($commit->id);

            if ($commit->isRestorable()) {
                $commit->restore();
                resolve(\App\Services\RepositoryMirrors::class)->commit($commit);
                $this->updateCommitHotScores->handle([$commit->threadRootId()]);
            }

            return $commit;
        }, attempts: 3);
    }
}
