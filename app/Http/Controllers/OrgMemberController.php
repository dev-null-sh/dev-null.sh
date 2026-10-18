<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrgMembers;
use App\Actions\DeleteOrgMember;
use App\Actions\UpdateOrgMemberRole;
use App\Enums\OrgRole;
use App\Http\Requests\CreateOrgMemberRequest;
use App\Http\Requests\DeleteOrgMemberRequest;
use App\Http\Requests\UpdateOrgMemberRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgMemberController
{
    public function store(CreateOrgMemberRequest $request, #[CurrentUser] User $user, Conversation $org, CreateOrgMembers $action): Response
    {
        $action->handle($user, $org, $request->members(), $request->boolean('share_history'));

        return response()->noContent();
    }

    public function update(UpdateOrgMemberRequest $request, #[CurrentUser] User $user, Conversation $org, User $member, UpdateOrgMemberRole $action): Response
    {
        $action->handle($user, $org, $member, $request->role() ?? OrgRole::Contributor);

        return response()->noContent();
    }

    public function destroy(DeleteOrgMemberRequest $request, #[CurrentUser] User $user, Conversation $org, User $member, DeleteOrgMember $action): Response
    {
        $action->handle($user, $org, $member);

        return response()->noContent();
    }
}
