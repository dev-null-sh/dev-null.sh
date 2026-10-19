<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RequestBranchVerification;
use App\Actions\UpdateBranchDomain;
use App\Http\Requests\UpdateBranchDomainRequest;
use App\Http\Requests\VerifyBranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;

final readonly class BranchVerificationController
{
    public function store(VerifyBranchRequest $request, Branch $branch, RequestBranchVerification $action): RedirectResponse
    {
        $action->handle($branch);

        return back();
    }

    public function update(UpdateBranchDomainRequest $request, Branch $branch, UpdateBranchDomain $action): RedirectResponse
    {
        $action->handle($branch, $request->string('domain')->toString());

        return back();
    }
}
