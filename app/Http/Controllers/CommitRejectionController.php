<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RejectCommit;
use App\Http\Requests\RejectCommitRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;

final readonly class CommitRejectionController
{
    public function store(RejectCommitRequest $request, Commit $commit, RejectCommit $action): RedirectResponse
    {
        /** @var string|null $reason */
        $reason = $request->validated('reason');

        $action->handle($commit, $reason);

        return back();
    }
}
