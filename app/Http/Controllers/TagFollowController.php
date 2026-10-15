<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\FollowTag;
use App\Actions\UnfollowTag;
use App\Http\Requests\CreateTagFollowRequest;
use App\Http\Requests\DeleteTagFollowRequest;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class TagFollowController
{
    public function store(CreateTagFollowRequest $request, #[CurrentUser] User $user, Tag $tag, FollowTag $action): RedirectResponse
    {
        $action->handle($user, $tag);

        return back();
    }

    public function destroy(DeleteTagFollowRequest $request, #[CurrentUser] User $user, Tag $tag, UnfollowTag $action): RedirectResponse
    {
        $action->handle($user, $tag);

        return back();
    }
}
