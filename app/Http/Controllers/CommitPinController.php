<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PinCommit;
use App\Actions\UnpinCommit;
use App\Http\Requests\PinCommitRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class CommitPinController
{
    public function update(PinCommitRequest $request, #[CurrentUser] User $user, Commit $commit, PinCommit $action): RedirectResponse
    {
        $action->handle($user, $commit);

        return to_route('user.show', $user);
    }

    public function destroy(PinCommitRequest $request, #[CurrentUser] User $user, Commit $commit, UnpinCommit $action): RedirectResponse
    {
        $action->handle($user, $commit);

        return to_route('user.show', $user);
    }
}
