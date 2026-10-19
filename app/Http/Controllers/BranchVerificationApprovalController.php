<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApproveBranchVerification;
use App\Http\Requests\ApproveBranchVerificationRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;

final readonly class BranchVerificationApprovalController
{
    public function store(ApproveBranchVerificationRequest $request, Branch $branch, ApproveBranchVerification $action): RedirectResponse
    {
        $action->handle($branch);

        return back();
    }
}
