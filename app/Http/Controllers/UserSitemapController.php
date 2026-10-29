<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowUserSitemapRequest;
use App\Queries\SitemapQuery;
use Illuminate\Http\Response;

final readonly class UserSitemapController
{
    public function show(ShowUserSitemapRequest $request, SitemapQuery $sitemaps): Response
    {
        return response()->view('sitemaps.urls', [
            'urls' => array_map(fn (array $user): array => [
                'loc' => route('user.show', $user['handle']),
                'lastmod' => $user['updated_at']->toAtomString(),
            ], $sitemaps->usersIn($request->month())),
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
