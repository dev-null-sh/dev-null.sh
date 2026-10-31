<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitSitemapRequest;
use App\Queries\SitemapQuery;
use Illuminate\Http\Response;

final readonly class CommitSitemapController
{
    public function show(ShowCommitSitemapRequest $request, SitemapQuery $sitemaps): Response
    {
        return response()->view('sitemaps.urls', [
            'urls' => array_map(fn (array $commit): array => [
                'loc' => route('commit.show', ['user' => $commit['handle'], 'commit' => $commit['id']]),
                'lastmod' => $commit['updated_at']->toAtomString(),
                'images' => array_map(fn (string $id): string => route('commit-image.show', $id), $commit['image_ids']),
            ], $sitemaps->commitsOn($request->day())),
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
