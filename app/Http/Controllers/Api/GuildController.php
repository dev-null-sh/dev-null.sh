<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiGuildsRequest;
use App\Http\Requests\ShowApiGuildRequest;
use App\Http\Resources\CommitResource;
use App\Http\Resources\GuildResource;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Queries\GuildCommitsQuery;
use App\Queries\GuildsQuery;
use App\Queries\ReplyPreviewsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class GuildController
{
    /**
     * The directory of guilds as the web's Guilds page lists it: 24 a page, the most members first, searched with ?q= in
     * their handle, name and description. Guilds are in preview: an account they aren't open to gets a 404.
     */
    public function index(ListApiGuildsRequest $request, #[CurrentUser] User $user, GuildsQuery $query): AnonymousResourceCollection
    {
        return GuildResource::collection($query->paginate($request->term(), $request->integer('page', 1), $user)->withQueryString());
    }

    /**
     * The guild's board as its page on the web shows it: 30 threads per page, newest first. Everyone gets the pin the
     * moderator set beside them, and it keeps its place on the board, which the web's page skips. One snapshot reads the
     * board, the pin and the guild, so they agree.
     */
    public function show(ShowApiGuildRequest $request, Guild $guild, #[CurrentUser] User $user, GuildsQuery $guilds, GuildCommitsQuery $commits, ReplyPreviewsQuery $replyPreviews): AnonymousResourceCollection
    {
        [$board, $pinnedCommit, $guild] = $commits->snapshot($user, function () use ($commits, $guilds, $guild, $user, $request, $replyPreviews): array {
            $guild = $guilds->find($guild, $user);
            $board = $commits->get($guild, $user, $request->integer('page', 1))->withQueryString();
            $pinnedCommit = $commits->pinned($guild, $user);

            $replyPreviews->load([...$board->items(), ...array_filter([$pinnedCommit])], $user);

            return [$board, $pinnedCommit, $guild];
        });

        return CommitResource::collection($board)->additional([
            'guild' => GuildResource::make($guild),
            'pinned_commit' => $pinnedCommit instanceof Commit ? CommitResource::make($pinnedCommit) : null,
            'can_moderate' => $guild->user_id === $user->id,
        ]);
    }
}
