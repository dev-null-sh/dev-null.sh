<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRejectionRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class CommitRejectionController
{
    public function store(CreateCommitRejectionRequest $request, #[CurrentUser] User $user, Commit $commit, UpdateCommitStatus $action): RedirectResponse
    {
        /** @var string|null $reason */
        $reason = $request->validated('reason');

        $action->handle($user, $commit, CommitStatus::Rejected, $reason);

        return back();
    }
}
