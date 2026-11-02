<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiRelatedCommitsRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\RelatedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class RelatedCommitController
{
    public function index(ListApiRelatedCommitsRequest $request, #[CurrentUser] ?User $user, Commit $commit, RelatedCommitsQuery $related): AnonymousResourceCollection
    {
        return CommitResource::collection($related->get($commit, $user));
    }
}
