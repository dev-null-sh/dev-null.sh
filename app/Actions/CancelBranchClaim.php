<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lets the requester (or an admin) withdraw a pending ownership claim.
 */
final readonly class CancelBranchClaim
{
    public function handle(Branch $branch, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $actor): Branch {
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            if (! $branch->hasPendingClaim()) {
                throw ValidationException::withMessages(['branch' => 'This branch is not waiting for a claim.']);
            }

            if ($branch->claim_requested_by !== $actor->id && ! $actor->isAdmin()) {
                throw ValidationException::withMessages(['branch' => 'Only the requester can cancel this claim.']);
            }

            $branch->forceFill([
                'claim_requested_at' => null,
                'claim_requested_by' => null,
            ])->save();

            return $branch->refresh();
        });
    }
}
