<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListSearchRequest;
use App\Http\Resources\GuildBadgeResource;
use App\Models\User;
use App\Queries\GuildMentionsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SearchController
{
    /**
     * Results are fetched by the client from CommitSearchController, so the page renders before the embedding is paid for.
     * Every query is a page of its own, so search engines follow the results without indexing them.
     */
    public function index(ListSearchRequest $request, #[CurrentUser] User $user, GuildMentionsQuery $guilds): Response
    {
        return Inertia::render('search/index', [
            'query' => $request->term(),
            'guildLookup' => function () use ($request, $user, $guilds): array {
                $handle = $request->guildHandle();
                $guild = $handle === null ? null : ($guilds->get([$request->term()], $user)[$handle] ?? null);

                return ['handle' => $handle, 'guild' => $guild === null ? null : GuildBadgeResource::make($guild)->resolve($request)];
            },
        ])->withViewData(['robots' => 'noindex, follow']);
    }
}
