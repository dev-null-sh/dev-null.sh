<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitApprovalRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final readonly class CommitApprovalController
{
    public function store(CreateCommitApprovalRequest $request, Commit $commit, UpdateCommitStatus $action): RedirectResponse
    {
        DB::transaction(function () use ($request, $commit, $action): void {
            $commit = Commit::query()->lockForUpdate()->findOrFail($commit->id);
            $request->ensureReviewed($commit);
            $action->handle($commit, CommitStatus::Approved);
        });

        return back();
    }
}
