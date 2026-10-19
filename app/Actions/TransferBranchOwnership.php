<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BranchRole;
use App\Enums\NotificationType;
use App\Models\Branch;
use App\Models\BranchMember;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Hands maintainer ownership to another person; the actor becomes a contributor.
 */
final readonly class TransferBranchOwnership
{
    public function handle(Branch $branch, User $actor, User $target): Branch
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages(['member' => 'Pick someone else to transfer ownership to.']);
        }

        if (! Conversation::userMayVerifyBranch($target, $branch->name)) {
            throw ValidationException::withMessages([
                'member' => 'This branch matches a Remotes org; only that org\'s maintainers can own it.',
            ]);
        }

        return DB::transaction(function () use ($branch, $actor, $target): Branch {
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            if (! $branch->isMaintainedBy($actor) && ! $actor->isAdmin()) {
                throw ValidationException::withMessages(['member' => 'Only maintainers can transfer ownership.']);
            }

            $actorRow = $branch->members()->where('user_id', $actor->id)->first();
            $targetRow = $branch->members()->where('user_id', $target->id)->first();

            if (! $actorRow instanceof BranchMember && ! $actor->isAdmin()) {
                throw ValidationException::withMessages(['member' => 'Only maintainers can transfer ownership.']);
            }

            if (! $targetRow instanceof BranchMember) {
                $remaining = Branch::MAX_MEMBERS - $branch->members()->count();

                if ($remaining <= 0) {
                    throw ValidationException::withMessages(['member' => 'This branch already has '.Branch::MAX_MEMBERS.' people.']);
                }

                if (! $actor->isAdmin() && ! $this->inFollowGraph($actor, $target)) {
                    throw ValidationException::withMessages([
                        'member' => "@{$target->username} must follow you or be someone you follow.",
                    ]);
                }

                $targetRow = $branch->members()->create([
                    'user_id' => $target->id,
                    'role' => BranchRole::Maintainer,
                ]);
            } else {
                $targetRow->forceFill(['role' => BranchRole::Maintainer])->save();
            }

            if ($actorRow instanceof BranchMember) {
                $actorRow->forceFill(['role' => BranchRole::Contributor])->save();
            }

            $now = now();

            Notification::query()->insertOrIgnore([[
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $target->id,
                'actor_id' => $actor->id,
                'type' => NotificationType::BranchOwnershipTransferred->value,
                'branch_id' => $branch->id,
                'key' => 'branch-ownership-transferred:'.$branch->id.':'.$target->id.':'.$now->getTimestampMs(),
                'created_at' => $now,
                'updated_at' => $now,
            ]]);

            return $branch->refresh();
        });
    }

    private function inFollowGraph(User $actor, User $member): bool
    {
        return $actor->following()->whereKey($member->id)->exists()
            || $actor->followers()->whereKey($member->id)->exists();
    }
}
