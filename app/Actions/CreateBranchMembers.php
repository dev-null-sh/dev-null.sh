<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Enums\BranchRole;
use App\Models\Notification;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateBranchMembers
{
    /**
     * @param  list<User>  $users
     */
    public function handle(Branch $branch, User $actor, array $users): void
    {
        DB::transaction(function () use ($branch, $actor, $users): void {
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $existing = $branch->members()->pluck('user_id')->all();
            $remaining = Branch::MAX_MEMBERS - count($existing);

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['members' => 'This branch already has '.Branch::MAX_MEMBERS.' people.']);
            }

            $toAdd = collect($users)
                ->unique(fn (User $user): string => $user->id)
                ->reject(fn (User $user): bool => in_array($user->id, $existing, true))
                ->take($remaining)
                ->values();

            if ($toAdd->isEmpty()) {
                return;
            }

            foreach ($toAdd as $user) {
                $branch->members()->create([
                    'user_id' => $user->id,
                    'role' => BranchRole::Contributor,
                ]);
            }

            $now = now();

            Notification::query()->insertOrIgnore($toAdd->map(fn (User $user): array => [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $user->id,
                'actor_id' => $actor->id,
                'type' => NotificationType::BranchMemberAdded->value,
                'branch_id' => $branch->id,
                'key' => 'branch-member-added:'.$branch->id.':'.$user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }
}
