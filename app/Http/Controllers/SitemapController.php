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
                ...array_map(fn (string $month): string => route('user-sitemap.show', $month), $sitemaps->userMonths()),
                ...array_map(fn (string $day): string => route('commit-sitemap.show', $day), $sitemaps->commitDays()),
            ],
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
