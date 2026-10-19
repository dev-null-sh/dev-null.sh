<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\BranchRole;
use App\Models\Notification;
use App\Models\Branch;
use App\Models\BranchMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class UpdateBranchMemberRole
{
    public function handle(Branch $branch, User $actor, User $member, BranchRole $role): void
    {
        DB::transaction(function () use ($branch, $actor, $member, $role): void {
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $row = $branch->members()->where('user_id', $member->id)->first();

            if (! $row instanceof BranchMember) {
                throw ValidationException::withMessages(['member' => 'That person is not on this branch.']);
            }

            if ($row->role === $role) {
                return;
            }

            if ($row->role === BranchRole::Maintainer && $role === BranchRole::Contributor) {
                $maintainerCount = $branch->members()->where('role', BranchRole::Maintainer)->count();

                if ($maintainerCount <= 1) {
                    throw ValidationException::withMessages(['role' => 'Promote someone else before demoting the last maintainer.']);
                }
            }

            $row->forceFill(['role' => $role])->save();

            Notification::query()->insertOrIgnore([[
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $member->id,
                'actor_id' => $actor->id,
                'type' => NotificationType::BranchRoleChanged->value,
                'branch_id' => $branch->id,
                'key' => 'branch-role-changed:'.$branch->id.':'.$member->id.':'.$role->value.':'.now()->getTimestampMs(),
                'reason' => $role->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]]);
        });
    }
}
