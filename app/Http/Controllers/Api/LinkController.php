<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiLinkRequest;
use App\Http\Resources\CommitResource;
use App\Models\LinkPreview;
use App\Models\User;
use App\Queries\LinkCommitsQuery;
use App\Queries\ReplyPreviewsQuery;
use App\Services\LinkPages;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;

final readonly class LinkController
{
    /**
     * The link's page as the web shows it to the signed user, or to a guest: its commits with the link, its stats and
     * the sort. A card no commit the viewer may see uses shows nothing of what it was.
     */
    public function show(ShowApiLinkRequest $request, #[CurrentUser] ?User $currentUser, LinkCommitsQuery $commits, ReplyPreviewsQuery $replyPreviews, LinkPages $pages): AnonymousResourceCollection
    {
        $url = $request->link();
        $isNewestFirst = $request->isNewestFirst();
        $link = LinkPreview::query()->where('url_hash', hash('sha256', $url))->first();
        $stats = $link instanceof LinkPreview ? $commits->stats($link, $currentUser) : ['commits' => 0, 'people' => 0];
        $link = $stats['commits'] > 0 ? $link : null;

        $page = new CursorPaginator([], 20, options: ['path' => Paginator::resolveCurrentPath()]);

        if ($link instanceof LinkPreview) {
            $page = $commits->builder($link, $currentUser, $isNewestFirst)->cursorPaginate(20)->appends($request->only('sort'));
            $replyPreviews->load($page->items(), $currentUser);
        }

        return CommitResource::collection($page)->additional([
            'url' => $url,
            'page_url' => $pages->url($url),
            'link' => $link,
            'stats' => $stats,
            'sort' => $isNewestFirst ? 'latest' : 'top',
        ]);
    }
}
