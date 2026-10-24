<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\SitemapQuery;
use App\Services\SitemapXml;
use Illuminate\Http\Response;

final readonly class CommitSitemapController
{
    public function show(int $page, SitemapQuery $sitemaps, SitemapXml $xml): Response
    {
        $urls = array_map(fn (array $commit): array => [
            'loc' => route('commit.show', ['user' => $commit['username'], 'commit' => $commit['id']]),
            'lastmod' => $commit['updated_at']->toAtomString(),
            'images' => array_map(fn (string $id): string => route('commit-image.show', $id), $commit['image_ids']),
        ], $sitemaps->commitsOn($page));

        return response($xml->render($urls), headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
