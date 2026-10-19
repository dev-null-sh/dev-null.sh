<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RejectBranchVerification;
use App\Http\Requests\RejectBranchVerificationRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;

final readonly class BranchVerificationRejectionController
{
    public function store(RejectBranchVerificationRequest $request, Branch $branch, RejectBranchVerification $action): RedirectResponse
    {
        $action->handle($branch);

        return back();
    }
}
