<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateGuild;
use App\Actions\UpdateGuild;
use App\Enums\UserUpdate;
use App\Exceptions\GuildModeratorRemoved;
use App\Http\Requests\CreateGuildRequest;
use App\Http\Requests\ListGuildsRequest;
use App\Http\Requests\ShowGuildRequest;
use App\Http\Requests\UpdateGuildRequest;
use App\Http\Resources\GuildResource;
use App\Http\Resources\ShownStreams;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Models\User;
use App\Queries\GuildCommitsQuery;
use App\Queries\GuildsQuery;
use App\Queries\JoinedGuildsQuery;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

final readonly class GuildController
{
    public function index(ListGuildsRequest $request, #[CurrentUser] User $user, GuildsQuery $query): Response
    {
        return Inertia::render('guild/index', [
            'query' => $request->term(),
            'guildPage' => fn () => $query->paginate($request->term(), $request->integer('page', 1), $user)
                ->through(fn (Guild $guild): array => ['id' => $guild->id, ...GuildResource::make($guild)->resolve($request)]),
            'guilds' => Inertia::live(fn (): array => $query->get($user, $request->visibleGuilds())
                ->map(fn (Guild $guild): array => ['id' => $guild->id, ...GuildResource::make($guild)->resolve($request)])->all(), UserUpdate::Guilds->value, new PrivateChannel($user)),
        ])->withViewData(['robots' => 'noindex, follow']);
    }

    public function store(CreateGuildRequest $request, #[CurrentUser] User $user, CreateGuild $action): RedirectResponse
    {
        $icon = $request->file('images.0');
        assert($icon instanceof UploadedFile || $icon === null);

        try {
            $guild = $action->handle($user, $request->string('handle')->value(), $request->string('name')->value(), $request->string('description')->value(), $request->string('emoji')->value(), $icon, $request->isFlagged());
        } catch (GuildModeratorRemoved) {
            return back()->withErrors(['handle' => CreateGuildRequest::RECLAIM_REFUSED]);
        }

        if ($guild instanceof GuildSubmission) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Your guild is waiting for review. It goes live once an admin approves it.']);

            return back();
        }

        return to_route('guild.show', $guild->handle);
    }

    public function show(ShowGuildRequest $request, Guild $guild, #[CurrentUser] User $user, GuildCommitsQuery $query, JoinedGuildsQuery $joinedGuilds, GuildsQuery $guilds): Response
    {
        $commits = $query->get($guild, $user, $request->integer('page', 1));

        return Inertia::render('guild/show', [
            'handle' => $guild->handle,
            'readFrom' => $joinedGuilds->readFrom($guild, $user),
            'guild' => Inertia::live(fn (): array => ['id' => $guild->id, ...GuildResource::make($guilds->get($user, [$guild->handle])->firstOrFail())->resolve($request)], UserUpdate::Guilds->value, new PrivateChannel($user)),
            'canModerate' => $guild->user_id === $user->id,
            'pinnedCommit' => ShownStreams::commit($query->pinned($guild, $user)),
            'commits' => $commits->currentPage() > 1
                ? Inertia::merge(ShownStreams::commits($commits))->matchOn('id')
                : ShownStreams::commits($commits),
            'page' => $commits->currentPage(),
            'hasMore' => $commits->hasMorePages(),
        ])->withViewData(['robots' => 'noindex, follow']);
    }

    public function update(UpdateGuildRequest $request, Guild $guild, #[CurrentUser] User $user, UpdateGuild $action): RedirectResponse
    {
        $icon = $request->file('images.0');
        assert($icon instanceof UploadedFile || $icon === null);

        try {
            $action->handle($guild, $user, $request->string('name')->value(), $request->string('description')->value(), $request->string('emoji')->value(), $icon, $request->boolean('remove_icon'), $request->isFlagged());
        } catch (GuildModeratorRemoved) {
            return back()->withErrors(['description' => 'You no longer moderate this guild.']);
        }

        if ($request->isFlagged()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Your changes are waiting for review. They go live once an admin approves them.']);
        }

        return to_route('guild.show', $guild->handle);
    }
}
