<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListSearchRequest;
use App\Http\Resources\ShownGuildMentions;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SearchController
{
    /**
     * Results are fetched by the client from CommitSearchController, so the page renders before the embedding is paid for.
     * Every query is a page of its own, so search engines follow the results without indexing them.
     */
    public function index(ListSearchRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('search/index', [
            'query' => $request->term(),
            'guildLookup' => fn (): array => [
                'handle' => $request->guildHandle(),
                'guild' => $request->guildHandle() === null ? null : (ShownGuildMentions::get([$request->term()], $user)[$request->guildHandle()] ?? null),
            ],
        ])->withViewData(['robots' => 'noindex, follow']);
    }
}
