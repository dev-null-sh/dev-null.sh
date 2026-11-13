<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitEscalation;
use App\Http\Requests\CreateCommitEscalationRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class CommitEscalationController
{
    public function store(CreateCommitEscalationRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitEscalation $action): RedirectResponse
    {
        $action->handle($user, $commit);

        return back();
    }
}
