<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBranchMembers;
use App\Actions\DeleteBranchMember;
use App\Actions\UpdateBranchMemberRole;
use App\Enums\BranchRole;
use App\Http\Requests\CreateBranchMemberRequest;
use App\Http\Requests\DeleteBranchMemberRequest;
use App\Http\Requests\UpdateBranchMemberRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class BranchMemberController
{
    public function store(CreateBranchMemberRequest $request, Branch $branch, #[CurrentUser] User $user, CreateBranchMembers $action): RedirectResponse
    {
        $action->handle($branch, $user, $request->members()->all());

        return back();
    }

    public function update(UpdateBranchMemberRequest $request, Branch $branch, User $member, #[CurrentUser] User $user, UpdateBranchMemberRole $action): RedirectResponse
    {
        $action->handle($branch, $user, $member, BranchRole::from($request->string('role')->toString()));

        return back();
    }

    public function destroy(DeleteBranchMemberRequest $request, Branch $branch, User $member, #[CurrentUser] User $user, DeleteBranchMember $action): RedirectResponse
    {
        $action->handle($branch, $user, $member);

        if ($user->id === $member->id) {
            return to_route('branch.show', $branch->name);
        }

        return back();
    }
}
