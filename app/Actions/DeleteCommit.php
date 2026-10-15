<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

final readonly class DeleteCommit
{
    /**
     * Soft deleting keeps the row for its replies, so notifications about the commit or about replies to it are removed here instead of by the foreign key.
     */
    public function handle(Commit $commit): void
    {
        DB::transaction(function () use ($commit): void {
            Notification::query()
                ->where('commit_id', $commit->id)
                ->orWhereIn('commit_id', $commit->replies()->select('id'))
                ->delete();

            $commit->delete();
        });
    }
}
