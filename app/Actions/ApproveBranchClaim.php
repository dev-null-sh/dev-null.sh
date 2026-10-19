<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BranchRole;
use App\Enums\NotificationType;
use App\Models\Branch;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Grants ownership after a moderator approves a pending claim.
 */
final readonly class ApproveBranchClaim
{
    public function handle(Branch $branch, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $actor): Branch {
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            if (! $branch->hasPendingClaim()) {
                throw ValidationException::withMessages(['branch' => 'This branch is not waiting for a claim.']);
            }

            if ($branch->isOwned()) {
                throw ValidationException::withMessages(['branch' => 'This branch already has maintainers.']);
            }

            $requesterId = $branch->claim_requested_by;
            assert(is_string($requesterId));

            $branch->members()->create([
                'user_id' => $requesterId,
                'role' => BranchRole::Maintainer,
            ]);

            $branch->forceFill([
                'claim_requested_at' => null,
                'claim_requested_by' => null,
            ])->save();

            $now = now();

            Notification::query()->insertOrIgnore([[
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $requesterId,
                'actor_id' => $actor->id,
                'type' => NotificationType::BranchClaimApproved->value,
                'branch_id' => $branch->id,
                'key' => 'branch-claim-approved:'.$branch->id.':'.$requesterId,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);

            return $branch->refresh();
        });
    }
}
