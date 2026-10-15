<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use Illuminate\Support\Facades\DB;

final readonly class ApproveCommits
{
    public function __construct(private NotifyAboutReplies $notifyAboutReplies)
    {
        //
    }

    /**
     * @param  array<int, string>  $commitIds
     */
    public function handle(array $commitIds): void
    {
        DB::transaction(function () use ($commitIds): void {
            $commits = Commit::query()
                ->pending()
                ->whereKey($commitIds)
                ->get();

            Commit::query()
                ->pending()
                ->whereKey($commits->modelKeys())
                ->update(['status' => CommitStatus::Approved]);

            $this->notifyAboutReplies->handle(...$commits);
        });
    }
}
