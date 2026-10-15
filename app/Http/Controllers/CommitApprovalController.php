<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApproveCommit;
use App\Http\Requests\ApproveCommitRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;

final readonly class CommitApprovalController
{
    public function store(ApproveCommitRequest $request, Commit $commit, ApproveCommit $action): RedirectResponse
    {
        $action->handle($commit);

        return back();
    }
}
