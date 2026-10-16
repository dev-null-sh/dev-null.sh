<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\SitemapQuery;
use Illuminate\Http\Response;

final readonly class UserSitemapController
{
    public function show(int $page, SitemapQuery $sitemaps): Response
    {
        return response()->view('sitemaps.urls', [
            'urls' => $sitemaps->usersOn($page)->map(fn (User $user): array => [
                'loc' => route('user.show', $user),
                'lastmod' => $user->updated_at->toAtomString(),
            ]),
        ], headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
