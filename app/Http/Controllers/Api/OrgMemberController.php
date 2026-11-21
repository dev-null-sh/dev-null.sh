<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrgMembers;
use App\Actions\DeleteOrgMember;
use App\Actions\UpdateOrgMemberRole;
use App\Enums\OrgRole;
use App\Http\Requests\CreateApiOrgMemberRequest;
use App\Http\Requests\DeleteApiOrgMemberRequest;
use App\Http\Requests\UpdateApiOrgMemberRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class OrgMemberController
{
    public function store(CreateApiOrgMemberRequest $request, #[CurrentUser] User $user, Conversation $org, CreateOrgMembers $action): Response
    {
        $action->handle($user, $org, $request->members(), $request->boolean('share_history'));

        return response()->noContent();
    }

    public function update(UpdateApiOrgMemberRequest $request, #[CurrentUser] User $user, Conversation $org, User $member, UpdateOrgMemberRole $action): Response
    {
        $action->handle($user, $org, $member, $request->role() ?? OrgRole::Contributor);

        return response()->noContent();
    }

    public function destroy(DeleteApiOrgMemberRequest $request, #[CurrentUser] User $user, Conversation $org, User $member, DeleteOrgMember $action): Response
    {
        $action->handle($user, $org, $member);

        return response()->noContent();
    }
}
