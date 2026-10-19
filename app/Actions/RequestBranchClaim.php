<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Queues an ownership claim for moderator review. Membership is not granted yet.
 */
final readonly class RequestBranchClaim
{
    public function __construct(private UpsertBranches $upsertBranches)
    {
        //
    }

    public function handle(User $user, string $name): Branch
    {
        return DB::transaction(function () use ($user, $name): Branch {
            $this->upsertBranches->handle([$name]);

            $branch = Branch::query()->where('name', $name)->lockForUpdate()->firstOrFail();

            if ($branch->isOwned()) {
                throw ValidationException::withMessages(['branch' => 'This branch already has maintainers.']);
            }

            if ($branch->hasPendingClaim()) {
                throw ValidationException::withMessages(['branch' => 'Someone already requested ownership of this branch.']);
            }

            if (! Conversation::userMayVerifyBranch($user, $name)) {
                throw ValidationException::withMessages([
                    'branch' => 'This branch matches a Remotes org; only that org\'s maintainers can claim it.',
                ]);
            }

            $branch->forceFill([
                'claim_requested_at' => now(),
                'claim_requested_by' => $user->id,
            ])->save();

            return $branch->refresh();
        });
    }
}
