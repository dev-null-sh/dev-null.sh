<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\SitemapQuery;
use App\Services\LinkPages;
use Illuminate\Http\Response;

final readonly class LinkSitemapController
{
    public function index(SitemapQuery $sitemaps, LinkPages $pages): Response
    {
        return response()->view('sitemaps.urls', [
            'urls' => array_map(fn (array $link): array => [
                'loc' => $pages->url($link['url']),
                'lastmod' => $link['updated_at']->toAtomString(),
            ], $sitemaps->links()),
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
