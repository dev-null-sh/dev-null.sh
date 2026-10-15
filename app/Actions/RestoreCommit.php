<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RestoreCommit
{
    public const int RECOVERY_SECONDS = 30;

    public function handle(User $user, Commit $commit): Commit
    {
        return DB::transaction(function () use ($user, $commit): Commit {
            $commit = Commit::withTrashed()->lockForUpdate()->findOrFail($commit->id);

            abort_unless($commit->user_id === $user->id, 403);

            if ($commit->deleted_at === null) {
                throw ValidationException::withMessages(['undo' => __('This commit is no longer deleted.')]);
            }

            if (now()->greaterThanOrEqualTo($commit->deleted_at->copy()->addSeconds(self::RECOVERY_SECONDS))) {
                throw ValidationException::withMessages(['undo' => __('The 30-second undo window has expired.')]);
            }

            $commit->restore();

            return $commit;
        }, attempts: 3);
    }
}
