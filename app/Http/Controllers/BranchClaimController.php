<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ClaimBranch;
use App\Http\Requests\ClaimBranchRequest;
use App\Models\User;
use App\Services\Branches;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class BranchClaimController
{
    public function store(ClaimBranchRequest $request, string $branch, #[CurrentUser] User $user, Branches $branches, ClaimBranch $action): RedirectResponse
    {
        $normalized = mb_strtolower($branch);
        abort_unless($branches->isValid($normalized), 404);

        $action->handle($user, $normalized, $request->string('domain')->toString());

        return to_route('branch.settings', $normalized);
    }
}
