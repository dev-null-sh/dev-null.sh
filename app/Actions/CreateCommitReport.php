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
    public function __construct(private CreateImageFiles $createImageFiles)
    {
        //
    }

    /**
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $imageDescriptions
     */
    public function handle(User $user, Commit $commit, string $reason, ?string $details, array $images = [], array $imageDescriptions = []): void
    {
        DB::transaction(function () use ($user, $commit, $reason, $details, $images, $imageDescriptions): void {
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
        });
    }
}
