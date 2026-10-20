<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRejectionRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final readonly class CommitRejectionController
{
    public function store(CreateCommitRejectionRequest $request, Commit $commit, UpdateCommitStatus $action): RedirectResponse
    {
        /** @var string|null $reason */
        $reason = $request->validated('reason');

        DB::transaction(function () use ($request, $commit, $action, $reason): void {
            $commit = Commit::query()->lockForUpdate()->findOrFail($commit->id);
            $request->ensureReviewed($commit);
            $action->handle($commit, CommitStatus::Rejected, $reason);
        });

        return back();
    }
}
