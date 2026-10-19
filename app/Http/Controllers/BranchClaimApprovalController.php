<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApproveBranchClaim;
use App\Http\Requests\ApproveBranchClaimRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class BranchClaimApprovalController
{
    public function store(
        ApproveBranchClaimRequest $request,
        Branch $branch,
        #[CurrentUser] User $user,
        ApproveBranchClaim $action,
    ): RedirectResponse {
        $action->handle($branch, $user);

        return back();
    }
}
