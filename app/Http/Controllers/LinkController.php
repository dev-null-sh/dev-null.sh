<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListLinksRequest;
use App\Models\LinkPreview;
use App\Models\User;
use App\Queries\LinkCommitsQuery;
use App\Queries\TopLinksQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A prototype gathering every commit about a link on one page, like the comments under it.
 */
final readonly class LinkController
{
    public function index(ListLinksRequest $request, #[CurrentUser] User $user, LinkCommitsQuery $commits, TopLinksQuery $topLinks): Response
    {
        $url = $request->link();

        if ($url === null) {
            return Inertia::render('links/index', [
                'url' => null,
                'topLinks' => $topLinks->get(),
            ]);
        }

        $link = LinkPreview::query()->where('url_hash', hash('sha256', $url))->first();

        return Inertia::render('links/index', [
            'url' => $url,
            'link' => $link,
            'stats' => $link === null ? ['commits' => 0, 'people' => 0] : $commits->stats($link),
            'commits' => $link === null ? null : Inertia::scroll(fn () => $commits->builder($link, $user)->cursorPaginate(20)),
        ]);
    }
}
