<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateGuildSubmissionApproval;
use App\Http\Requests\CreateGuildSubmissionApprovalRequest;
use Illuminate\Http\RedirectResponse;

final readonly class GuildSubmissionApprovalController
{
    public function store(CreateGuildSubmissionApprovalRequest $request, CreateGuildSubmissionApproval $action): RedirectResponse
    {
        $action->handle($request->submission());

        return back();
    }
}
