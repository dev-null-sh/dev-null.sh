<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RejectBranchClaim;
use App\Http\Requests\RejectBranchClaimRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class BranchClaimRejectionController
{
    public function store(
        RejectBranchClaimRequest $request,
        Branch $branch,
        #[CurrentUser] User $user,
        RejectBranchClaim $action,
    ): RedirectResponse {
        $action->handle($branch, $user);

        return back();
    }
}
