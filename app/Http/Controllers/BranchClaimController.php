<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CancelBranchClaim;
use App\Actions\RequestBranchClaim;
use App\Http\Requests\CancelBranchClaimRequest;
use App\Http\Requests\ClaimBranchRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Branches;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class BranchClaimController
{
    public function store(
        ClaimBranchRequest $request,
        string $branch,
        #[CurrentUser] User $user,
        Branches $branches,
        RequestBranchClaim $action,
    ): RedirectResponse {
        $normalized = mb_strtolower($branch);
        abort_unless($branches->isValid($normalized), 404);

        $action->handle($user, $normalized);

        return to_route('branch.show', $normalized);
    }

    public function destroy(
        CancelBranchClaimRequest $request,
        Branch $branch,
        #[CurrentUser] User $user,
        CancelBranchClaim $action,
    ): RedirectResponse {
        $action->handle($branch, $user);

        return to_route('branch.show', $branch->name);
    }
}
