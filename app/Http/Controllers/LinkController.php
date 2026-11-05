<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListLinksRequest;
use App\Http\Resources\ShownStreams;
use App\Models\LinkPreview;
use App\Models\User;
use App\Queries\LinkCommitsQuery;
use App\Queries\TopLinksQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A prototype gathering every commit about a link on one page, like the comments under it.
 */
final readonly class LinkController
{
    public function lookup(ListLinksRequest $request): RedirectResponse
    {
        $url = $request->link();

        if ($url === null) {
            return to_route('link.index');
        }

        $token = Str::random(24);
        $lookups = $request->session()->get('link_lookups', []);
        $lookups[$token] = $url;
        $request->session()->put('link_lookups', array_slice($lookups, -10, null, true));

        return to_route('link.index', ['lookup' => $token]);
    }

    public function index(ListLinksRequest $request, #[CurrentUser] User $user, LinkCommitsQuery $commits, TopLinksQuery $topLinks): Response
    {
        $url = $request->link();

        if ($url === null && $request->filled('lookup')) {
            $token = $request->string('lookup')->value();
            $url = $request->session()->get('link_lookups.'.$token);
            abort_unless(is_string($url), 404);
        }

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
            'commits' => $link === null ? null : Inertia::scroll(fn (): CursorPaginator => ShownStreams::page($commits->builder($link, $user)->cursorPaginate(20))),
        ]);
    }
}
