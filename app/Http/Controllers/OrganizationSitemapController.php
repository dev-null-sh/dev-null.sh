<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowOrganizationSitemapRequest;
use App\Queries\SitemapQuery;
use Illuminate\Http\Response;

final readonly class OrganizationSitemapController
{
    public function show(ShowOrganizationSitemapRequest $request, SitemapQuery $sitemaps): Response
    {
        return response()->view('sitemaps.urls', [
            'urls' => array_map(fn (array $organization): array => [
                'loc' => route('user.show', $organization['handle']),
                'lastmod' => $organization['updated_at']->toAtomString(),
            ], $sitemaps->organizationsIn($request->month())),
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
