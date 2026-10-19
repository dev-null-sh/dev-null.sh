<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBranchWatch;
use App\Actions\DeleteBranchWatch;
use App\Actions\UpsertBranches;
use App\Http\Requests\CreateBranchWatchRequest;
use App\Http\Requests\DeleteBranchWatchRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Branches;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class BranchWatchController
{
    public function store(
        CreateBranchWatchRequest $request,
        string $branch,
        #[CurrentUser] User $user,
        Branches $branches,
        UpsertBranches $upsertBranches,
        CreateBranchWatch $action,
    ): RedirectResponse {
        $normalized = mb_strtolower($branch);
        abort_unless($branches->isValid($normalized), 404);

        $upsertBranches->handle([$normalized]);
        $action->handle($user, Branch::query()->where('name', $normalized)->firstOrFail());

        return back();
    }

    public function destroy(
        DeleteBranchWatchRequest $request,
        string $branch,
        #[CurrentUser] User $user,
        Branches $branches,
        UpsertBranches $upsertBranches,
        DeleteBranchWatch $action,
    ): RedirectResponse {
        $normalized = mb_strtolower($branch);
        abort_unless($branches->isValid($normalized), 404);

        $upsertBranches->handle([$normalized]);
        $action->handle($user, Branch::query()->where('name', $normalized)->firstOrFail());

        return back();
    }
}
