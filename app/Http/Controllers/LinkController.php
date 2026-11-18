<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowLinkRequest;
use App\Http\Resources\ShownStreams;
use App\Models\LinkPreview;
use App\Models\User;
use App\Queries\LinkCommitsQuery;
use App\Services\LinkPages;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class LinkController
{
    /**
     * Every commit about a link, the link being their topic. The page is public like a commit's, at the link as commits
     * store it, and only indexed once enough people talk about the link. A card no commit the viewer may see uses, shared
     * in a message or a hidden commit, shows nothing of what it was.
     */
    public function show(ShowLinkRequest $request, #[CurrentUser] ?User $user, LinkCommitsQuery $commits, LinkPages $pages, PageMetadata $metadata): Response|RedirectResponse
    {
        $url = $request->link();
        $pageUrl = $pages->url($url);
        assert(is_string($pageUrl));

        if ($url !== $request->requestedLink()) {
            $query = $request->getQueryString();

            return redirect()->to($query === null ? $pageUrl : "{$pageUrl}?{$query}", 301);
        }

        $link = LinkPreview::query()->where('url_hash', hash('sha256', $url))->first();
        $stats = $link instanceof LinkPreview ? $commits->stats($link, $user) : ['commits' => 0, 'people' => 0];
        $link = $stats['commits'] > 0 ? $link : null;
        $isNewestFirst = $request->isNewestFirst();

        $preview = $user instanceof User || $request->inertia() ? null : $metadata->link($url, $pageUrl, $link, $stats);

        return Inertia::render('link/show', [
            'url' => $url,
            'pageUrl' => $pageUrl,
            'link' => $link,
            'iconUrl' => $link?->icon_url,
            'sort' => $isNewestFirst ? 'latest' : 'top',
            'stats' => $stats,
            'commits' => $link instanceof LinkPreview
                ? Inertia::scroll(fn (): CursorPaginator => ShownStreams::page($commits->builder($link, $user, $isNewestFirst)->cursorPaginate(20)))
                : null,
        ])->withViewData(['preview' => $preview]);
    }
}
