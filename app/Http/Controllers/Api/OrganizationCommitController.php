<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiOrganizationCommitsRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;
use App\Queries\OrganizationCommitsQuery;
use App\Queries\ReplyPreviewsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class OrganizationCommitController
{
    /**
     * A signed request gets the organization's Commits tab as the web shows it to that user.
     */
    public function index(ListApiOrganizationCommitsRequest $request, #[CurrentUser] ?User $currentUser, OrganizationCommitsQuery $commits, ReplyPreviewsQuery $replyPreviews): AnonymousResourceCollection
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        if ($currentUser instanceof User) {
            $signedCommits = $commits->builder($organization, $currentUser)->cursorPaginate(20);

            $replyPreviews->load($signedCommits->items(), $currentUser);

            return CommitResource::collection($signedCommits);
        }

        return CommitResource::collection(Commit::query()
            ->where('author_id', $organization->id)
            ->approved()
            ->topLevel()
            ->withPublicPreview()
            ->latest()
            ->latest('id')
            ->cursorPaginate(20));
    }
}
