<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitClaim;
use App\Http\Requests\UpdateCommitClaimRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class CommitClaimController
{
    public function update(UpdateCommitClaimRequest $request, #[CurrentUser] User $user, Commit $commit, UpdateCommitClaim $action): RedirectResponse
    {
        $action->handle($user, $commit);

        return back();
    }
}
