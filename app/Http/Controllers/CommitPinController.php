<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteCommitPin;
use App\Actions\UpdateCommitPin;
use App\Http\Requests\DeleteCommitPinRequest;
use App\Http\Requests\UpdateCommitPinRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class CommitPinController
{
    public function update(UpdateCommitPinRequest $request, #[CurrentUser] User $user, Commit $commit, UpdateCommitPin $action): RedirectResponse
    {
        $action->handle($user, $commit);

        return to_route('user.show', $user);
    }

    public function destroy(DeleteCommitPinRequest $request, #[CurrentUser] User $user, Commit $commit, DeleteCommitPin $action): RedirectResponse
    {
        $action->handle($user, $commit);

        return to_route('user.show', $user);
    }
}
