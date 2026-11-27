<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateGuild;
use App\Actions\UpdateGuild;
use App\Exceptions\GuildModeratorRemoved;
use App\Http\Requests\CreateApiGuildRequest;
use App\Http\Requests\CreateGuildRequest;
use App\Http\Requests\ListApiGuildsRequest;
use App\Http\Requests\ShowApiGuildRequest;
use App\Http\Requests\UpdateApiGuildRequest;
use App\Http\Resources\CommitResource;
use App\Http\Resources\GuildResource;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Models\User;
use App\Queries\GuildCommitsQuery;
use App\Queries\GuildsQuery;
use App\Queries\ReplyPreviewsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GuildController
{
    public function index(ListApiGuildsRequest $request, #[CurrentUser] User $user, GuildsQuery $query): AnonymousResourceCollection
    {
        return GuildResource::collection($query->paginate($request->term(), $request->integer('page', 1), $user)->withQueryString());
    }

    /**
     * Creates the guild, or claims a paused one, with the user as its moderator and first member, as the web does. A
     * guild the moderation flags waits for an admin instead, so it answers 202 with the web's message.
     */
    public function store(CreateApiGuildRequest $request, #[CurrentUser] User $user, CreateGuild $action, GuildsQuery $guilds): JsonResponse
    {
        $icon = $request->file('images.0');
        assert($icon instanceof UploadedFile || $icon === null);

        try {
            $guild = $action->handle($user, $request->string('handle')->value(), $request->string('name')->value(), $request->string('description')->value(), $request->string('emoji')->value(), $icon, $request->isFlagged());
        } catch (GuildModeratorRemoved) {
            return $this->handleRefused(CreateGuildRequest::RECLAIM_REFUSED);
        }

        if ($guild instanceof GuildSubmission) {
            return response()->json(['message' => 'Your guild is waiting for review. It goes live once an admin approves it.'], 202);
        }

        // Another creation of the same hashtag won the race after validation, and the action leaves it to its moderator...
        if ($guild->user_id !== $user->id) {
            return $this->handleRefused(CreateGuildRequest::guildMessages()['handle.unique']);
        }

        return GuildResource::make($guilds->find($guild, $user))->response()->setStatusCode(201);
    }

    /**
     * The guild's board as its page on the web shows it: 30 matching commits per page, newest first. Everyone gets the
     * moderator's pin beside them; when it also matches directly, the web skips its duplicate in the list. One snapshot
     * reads the board, the pin and the guild, so they agree.
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

    /**
     * Edits the guild's display name, description, emoji and icon, as the web does. An edit the moderation flags waits
     * for an admin instead, so it answers 202 with the web's message, and the guild stays as it was.
     */
    public function update(UpdateApiGuildRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuild $action, GuildsQuery $guilds): JsonResponse
    {
        $icon = $request->file('images.0');
        assert($icon instanceof UploadedFile || $icon === null);

        try {
            $action->handle($guild, $user, $request->string('name')->value(), $request->string('description')->value(), $request->string('emoji')->value(), $icon, $request->boolean('remove_icon'), $request->isFlagged());
        } catch (GuildModeratorRemoved) {
            throw new NotFoundHttpException;
        }

        if ($request->isFlagged()) {
            return response()->json(['message' => 'Your changes are waiting for review. They go live once an admin approves them.'], 202);
        }

        return GuildResource::make($guilds->find($guild, $user))->response();
    }

    /**
     * A race lost after validation, answered as the validation would have answered it.
     */
    private function handleRefused(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['handle' => [$message]]], 422);
    }
}
