<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Branch;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Drops a pending ownership claim without granting membership.
 */
final readonly class RejectBranchClaim
{
    public function handle(Branch $branch, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $actor): Branch {
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            if (! $branch->hasPendingClaim()) {
                throw ValidationException::withMessages(['branch' => 'This branch is not waiting for a claim.']);
            }

            $requesterId = $branch->claim_requested_by;
            assert(is_string($requesterId));

            $branch->forceFill([
                'claim_requested_at' => null,
                'claim_requested_by' => null,
            ])->save();

            $now = now();

            Notification::query()->insertOrIgnore([[
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $requesterId,
                'actor_id' => $actor->id,
                'type' => NotificationType::BranchClaimRejected->value,
                'branch_id' => $branch->id,
                'key' => 'branch-claim-rejected:'.$branch->id.':'.$requesterId.':'.$now->getTimestampMs(),
                'created_at' => $now,
                'updated_at' => $now,
            ]]);

            return $branch->refresh();
        });
    }
}
