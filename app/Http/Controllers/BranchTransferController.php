<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\TransferBranchOwnership;
use App\Http\Requests\TransferBranchOwnershipRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class BranchTransferController
{
    public function store(
        TransferBranchOwnershipRequest $request,
        Branch $branch,
        #[CurrentUser] User $user,
        TransferBranchOwnership $action,
    ): RedirectResponse {
        $action->handle($branch, $user, $request->target());

        return back();
    }
}
