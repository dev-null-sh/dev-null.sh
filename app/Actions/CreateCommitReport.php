<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommitReport
{
    public function __construct(private CreateImageFiles $createImageFiles, private UpdateReportToChildSafety $updateToChildSafety, private CreateChildSafetyAlert $createAlert)
    {
        //
    }

    /**
     * Reporting a commit again keeps the first report, unless the new one is about child safety and the first is open,
     * which raises it. Moderators hear about a new child safety report and a raised one, not a repeat.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $imageDescriptions
     */
    public function handle(User $user, Commit $commit, string $reason, ?string $details, array $images = [], array $imageDescriptions = []): void
    {
        $alert = DB::transaction(function () use ($user, $commit, $reason, $details, $images, $imageDescriptions): bool {
            // Match account deletion's lock order and recheck models that may have been deleted since the request began.
            User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
            Commit::query()->whereKey($commit->id)->sharedLock()->firstOrFail();

            $report = CommitReport::query()->createOrFirst([
                'commit_id' => $commit->id,
                'reporter_id' => $user->id,
            ], [
                'reason' => $reason,
                'details' => $details,
            ]);

            if ($report->wasRecentlyCreated && $images !== []) {
                $report->forceFill([
                    'images' => $this->createImageFiles->handle($images, $imageDescriptions, 'report-images', encrypted: true),
                ])->save();
            }

            return $reason === CommitReport::CHILD_SAFETY
                && ($report->wasRecentlyCreated || $this->updateToChildSafety->handle($report, $details));
        });

        if ($alert) {
            $this->createAlert->handle(route('reported-commit.index'));
        }
    }
}
