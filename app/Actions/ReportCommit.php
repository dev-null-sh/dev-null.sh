<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class ReportCommit
{
    public function handle(User $user, Commit $commit, string $reason, ?string $details): void
    {
        DB::transaction(function () use ($user, $commit, $reason, $details): void {
            $target = Commit::query()->lockForUpdate()->findOrFail($commit->id);
            Gate::authorize('create', [CommitReport::class, $target]);

            CommitReport::query()->firstOrCreate([
                'commit_id' => $target->id,
                'reporter_id' => $user->id,
            ], [
                'reason' => $reason,
                'details' => $details,
            ]);
        });
    }
}
