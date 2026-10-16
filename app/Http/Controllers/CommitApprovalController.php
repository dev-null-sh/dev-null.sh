<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitApprovalRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class CommitApprovalController
{
    public function store(CreateCommitApprovalRequest $request, #[CurrentUser] User $user, Commit $commit, UpdateCommitStatus $action): RedirectResponse
    {
        $action->handle($user, $commit, CommitStatus::Approved);

        return back();
    }
}
