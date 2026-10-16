<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\SitemapQuery;
use Illuminate\Http\Response;

final readonly class SitemapController
{
    public function index(SitemapQuery $sitemaps): Response
    {
        return response()->view('sitemaps.index', [
            'sitemaps' => [
                ...array_map(fn (int $page): string => route('user-sitemap.show', $page), range(1, max(1, $sitemaps->userPages()))),
                ...array_map(fn (int $page): string => route('commit-sitemap.show', $page), range(1, max(1, $sitemaps->commitPages()))),
            ],
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
