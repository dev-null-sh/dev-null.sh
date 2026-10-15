<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use Illuminate\Support\Facades\DB;

final readonly class ApproveCommit
{
    public function __construct(private NotifyAboutReplies $notifyAboutReplies)
    {
        //
    }

    public function handle(Commit $commit): void
    {
        DB::transaction(function () use ($commit): void {
            $commit->update(['status' => CommitStatus::Approved]);

            $this->notifyAboutReplies->handle($commit);
        });
    }
}
