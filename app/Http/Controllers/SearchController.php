<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListSearchRequest;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SearchController
{
    /**
     * Results are mocked on the client until the page is wired to CommitSearchQuery.
     */
    public function index(ListSearchRequest $request): Response
    {
        return Inertia::render('search/index', [
            'query' => $request->term(),
        ]);
    }
}
