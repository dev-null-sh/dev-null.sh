<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\BranchMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteBranchMember
{
    public function handle(Branch $branch, User $actor, User $member): void
    {
        DB::transaction(function () use ($branch, $actor, $member): void {
            $branch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $row = $branch->members()->where('user_id', $member->id)->first();

            if (! $row instanceof BranchMember) {
                return;
            }

            $leavingSelf = $actor->id === $member->id;

            if (! $leavingSelf && ! $branch->isMaintainedBy($actor) && ! $actor->isAdmin()) {
                throw ValidationException::withMessages(['member' => 'Only maintainers can remove other people.']);
            }

            if ($row->role === BranchRole::Maintainer) {
                $maintainerCount = $branch->members()->where('role', BranchRole::Maintainer)->count();

                if ($maintainerCount <= 1) {
                    $heir = $branch->members()
                        ->where('role', BranchRole::Contributor)
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->first();

                    if ($heir instanceof BranchMember) {
                        $heir->forceFill(['role' => BranchRole::Maintainer])->save();
                    }
                }
            }

            $row->delete();

            if (! $branch->members()->exists()) {
                $branch->forceFill([
                    'domain' => null,
                    'verification_token' => null,
                    'verification_requested_at' => null,
                    'verified_at' => null,
                    'verified_domain' => null,
                    'description' => null,
                    'domain_description' => null,
                    'og_image_url' => null,
                    'brand_color' => null,
                    'claim_requested_at' => null,
                    'claim_requested_by' => null,
                ])->save();
            }
        });
    }
}
