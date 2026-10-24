<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CommitStatus;
use App\Http\Requests\ListCommitSearchRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitSearchQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class CommitSearchController
{
    /**
     * The commits nearest in meaning to the term, as the web's search finds them: 20, or as many as asked for up to 100,
     * and fewer when the decision model drops some. A guest sees which commit a reply answers only when that commit is
     * approved, as everywhere a guest reads commits.
     */
    public function index(ListCommitSearchRequest $request, #[CurrentUser] ?User $currentUser, CommitSearchQuery $search): AnonymousResourceCollection
    {
        /** @var Collection<int, Commit> $commits */
        $commits = new Collection($search->get($request->string('q')->value(), $currentUser, $request->limit())->all());

        if (! $currentUser instanceof User) {
            $commits->load(['parent' => fn (BelongsTo $query): BelongsTo => $query->where('status', CommitStatus::Approved)]);
        }

        return CommitResource::collection($commits);
    }
}
