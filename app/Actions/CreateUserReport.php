<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CommitReport;
use App\Models\User;
use App\Models\UserReport;

final readonly class CreateUserReport
{
    public function __construct(private UpdateReportToChildSafety $updateToChildSafety, private CreateChildSafetyAlert $createAlert)
    {
        //
    }

    /**
     * Reporting someone again while the first report is open keeps the first report, unless the new one is about child
     * safety, which raises it. Moderators hear about a new child safety report and a raised one, not a repeat.
     */
    public function handle(User $reporter, User $user, string $reason, ?string $details): void
    {
        $report = UserReport::query()->whereNull('dismissed_at')->createOrFirst([
            'user_id' => $user->id,
            'reporter_id' => $reporter->id,
        ], [
            'reason' => $reason,
            'details' => $details,
        ]);

        if ($reason !== CommitReport::CHILD_SAFETY) {
            return;
        }

        if ($report->wasRecentlyCreated || $this->updateToChildSafety->handle($report, $details)) {
            $this->createAlert->handle(route('reported-user.index'));
        }
    }
}
