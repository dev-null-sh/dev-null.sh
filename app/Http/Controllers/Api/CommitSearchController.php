<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiCommitSearchRequest;
use App\Http\Resources\CommitResource;
use App\Models\User;
use App\Queries\CommitSearchQuery;
use App\Queries\ReplyPreviewsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class CommitSearchController
{
    /**
     * The commits nearest in meaning to the term, closest first, as the web's search finds them, which may be fewer than
     * asked for once the irrelevant ones are left out.
     */
    public function index(ListApiCommitSearchRequest $request, #[CurrentUser] User $user, CommitSearchQuery $search, ReplyPreviewsQuery $replyPreviews): AnonymousResourceCollection
    {
        $commits = $search->get($request->string('q')->value(), $user, $request->limit());

        $replyPreviews->load($commits->all(), $user);

        return CommitResource::collection($commits);
    }
}
