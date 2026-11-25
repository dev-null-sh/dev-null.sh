<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGuildMembership;
use App\Http\Requests\CreateApiGuildMembershipRequest;
use App\Http\Requests\DeleteApiGuildMembershipRequest;
use App\Http\Requests\ListApiGuildMembersRequest;
use App\Http\Resources\UserResource;
use App\Models\Guild;
use App\Models\User;
use App\Queries\GuildMembersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final readonly class GuildMembershipController
{
    /**
     * A guild's members as its page lists them, 50 a page, with whether the signed-in user follows each one, and whether
     * each one follows them. The response also has the moderator's id, or null while the guild is paused.
     */
    public function index(ListApiGuildMembersRequest $request, Guild $guild, #[CurrentUser] User $user, GuildMembersQuery $members): AnonymousResourceCollection
    {
        $page = $members->builder($guild)
            ->withExists([
                'followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($user->id),
                'following as follows_you' => fn (Builder $query): Builder => $query->whereKey($user->id),
            ])
            ->paginate(50)
            ->withQueryString();

        return UserResource::collection($page)->additional(['moderator_id' => $guild->user_id]);
    }

    /**
     * Joining clears a mute of the guild, as on the web.
     */
    public function store(CreateApiGuildMembershipRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMembership $action): Response
    {
        $action->handle($user, $guild, true);

        return response()->noContent();
    }

    public function destroy(DeleteApiGuildMembershipRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuildMembership $action): Response
    {
        $action->handle($user, $guild, false);

        return response()->noContent();
    }
}
