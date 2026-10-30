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
                ...array_map(fn (array $month): array => [
                    'loc' => route('user-sitemap.show', $month['month']),
                    'lastmod' => $month['updated_at']->toAtomString(),
                ], $sitemaps->userMonths()),
                ...array_map(fn (array $month): array => [
                    'loc' => route('organization-sitemap.show', $month['month']),
                    'lastmod' => $month['updated_at']->toAtomString(),
                ], $sitemaps->organizationMonths()),
                ...array_map(fn (array $day): array => [
                    'loc' => route('commit-sitemap.show', $day['day']),
                    'lastmod' => $day['updated_at']->toAtomString(),
                ], $sitemaps->commitDays()),
            ],
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
