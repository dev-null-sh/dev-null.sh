<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserCoverImageApproval;
use App\Http\Requests\CreateUserCoverImageApprovalRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final readonly class UserCoverImageApprovalController
{
    public function store(CreateUserCoverImageApprovalRequest $request, User $user, UpdateUserCoverImageApproval $action): RedirectResponse
    {
        $action->handle($user);

        return back();
    }
}
