<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTagMembers;
use App\Actions\DeleteTagMember;
use App\Actions\UpdateTagMemberRole;
use App\Enums\TagRole;
use App\Http\Requests\CreateTagMemberRequest;
use App\Http\Requests\DeleteTagMemberRequest;
use App\Http\Requests\UpdateTagMemberRequest;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class TagMemberController
{
    public function store(CreateTagMemberRequest $request, Tag $tag, #[CurrentUser] User $user, CreateTagMembers $action): RedirectResponse
    {
        $action->handle($tag, $user, $request->members()->all());

        return back();
    }

    public function update(UpdateTagMemberRequest $request, Tag $tag, User $member, #[CurrentUser] User $user, UpdateTagMemberRole $action): RedirectResponse
    {
        $action->handle($tag, $user, $member, TagRole::from($request->string('role')->toString()));

        return back();
    }

    public function destroy(DeleteTagMemberRequest $request, Tag $tag, User $member, #[CurrentUser] User $user, DeleteTagMember $action): RedirectResponse
    {
        $action->handle($tag, $user, $member);

        return back();
    }
}
