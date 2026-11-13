<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateGuild;
use App\Actions\DeleteGuild;
use App\Actions\UpdateGuild;
use App\Exceptions\GuildModeratorRemoved;
use App\Http\Requests\CreateGuildRequest;
use App\Http\Requests\DeleteGuildRequest;
use App\Http\Requests\ListGuildsRequest;
use App\Http\Requests\ShowGuildRequest;
use App\Http\Requests\UpdateGuildRequest;
use App\Http\Resources\ShownStreams;
use App\Models\Guild;
use App\Models\User;
use App\Queries\GuildCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

final readonly class GuildController
{
    public function index(ListGuildsRequest $request): Response
    {
        return Inertia::render('guild/index')->withViewData(['robots' => 'noindex, follow']);
    }

    public function store(CreateGuildRequest $request, #[CurrentUser] User $user, CreateGuild $action): RedirectResponse
    {
        $icon = $request->file('images.0');
        assert($icon instanceof UploadedFile || $icon === null);

        try {
            $guild = $action->handle($user, $request->string('handle')->value(), $request->string('name')->value(), $request->string('description')->value(), $request->string('emoji')->value(), $icon);
        } catch (GuildModeratorRemoved) {
            return back()->withErrors(['handle' => 'You cannot reclaim a guild you were removed from.']);
        }

        return to_route('guild.show', $guild->handle);
    }

    public function show(ShowGuildRequest $request, Guild $guild, #[CurrentUser] User $user, GuildCommitsQuery $query): Response
    {
        $commits = $query->get($guild, $user, $request->integer('page', 1));

        return Inertia::render('guild/show', [
            'handle' => $guild->handle,
            'canModerate' => $guild->user_id === $user->id,
            'commits' => ShownStreams::commits($commits),
            'page' => $commits->currentPage(),
            'hasMore' => $commits->hasMorePages(),
        ])->withViewData(['robots' => 'noindex, follow']);
    }

    public function update(UpdateGuildRequest $request, Guild $guild, UpdateGuild $action): RedirectResponse
    {
        $icon = $request->file('images.0');
        assert($icon instanceof UploadedFile || $icon === null);
        $action->handle($guild, $request->string('description')->value(), $request->string('emoji')->value(), $icon, $request->boolean('remove_icon'));

        return to_route('guild.show', $guild->handle);
    }

    public function destroy(DeleteGuildRequest $request, Guild $guild, DeleteGuild $action): RedirectResponse
    {
        $action->handle($guild);

        return to_route('search.index', ['q' => '#'.$guild->handle]);
    }
}
