<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListCommitSearchRequest;
use App\Http\Resources\CommitResource;
use App\Models\User;
use App\Queries\CommitSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class CommitSearchController
{
    /**
     * Commits nearest in meaning to the query, as the web search does. A signed request gets them as the web shows
     * them to that user, and leaves out muted people and people across a block.
     */
    public function index(ListCommitSearchRequest $request, #[CurrentUser] ?User $currentUser, CommitSearchQuery $search): AnonymousResourceCollection
    {
        return CommitResource::collection($search->get($request->string('q')->value(), $currentUser, $request->limit()));
    }
}
