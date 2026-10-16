<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Commit;
use App\Queries\SitemapQuery;
use Illuminate\Http\Response;

final readonly class CommitSitemapController
{
    public function show(int $page, SitemapQuery $sitemaps): Response
    {
        return response()->view('sitemaps.urls', [
            'urls' => $sitemaps->commitsOn($page)->map(fn (Commit $commit): array => [
                'loc' => route('commit.show', [$commit->user, $commit]),
                'lastmod' => $commit->updated_at->toAtomString(),
            ]),
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
