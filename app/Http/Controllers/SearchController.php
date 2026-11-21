<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListSearchRequest;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SearchController
{
    /**
     * Results are fetched by the client from CommitSearchController, so the page renders before the embedding is paid for.
     * Every query is a page of its own, so search engines follow the results without indexing them.
     */
    public function index(ListSearchRequest $request): Response
    {
        return Inertia::render('search/index', [
            'query' => $request->term(),
        ])->withViewData(['robots' => 'noindex, follow']);
    }
}
