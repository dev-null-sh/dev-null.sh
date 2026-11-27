<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateGuildMembership;
use App\Http\Requests\ListGuildMembersRequest;
use App\Http\Requests\UpdateGuildMembershipRequest;
use App\Models\Guild;
use App\Models\User;
use App\Queries\GuildMembersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class GuildMembershipController
{
    public function index(ListGuildMembersRequest $request, Guild $guild, GuildMembersQuery $members): Response
    {
        return Inertia::render('guild/members', [
            'guild' => $guild,
            'users' => Inertia::scroll(fn () => $members->builder($guild)
                ->paginate(50)->through(fn (User $member): array => [
                    'id' => $member->id,
                    'handle' => $member->handle,
                    'name' => $member->name,
                    'avatar_url' => $member->avatar_url,
                    'bio' => $member->bio,
                    'lane' => $member->lane(),
                    'is_moderator' => $member->id === $guild->user_id,
                ])),
        ])->withViewData(['robots' => 'noindex, follow']);
    }

    public function update(UpdateGuildMembershipRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMembership $action): RedirectResponse
    {
        $action->handle($user, $guild, $request->boolean('joined'));

        return back();
    }
}
