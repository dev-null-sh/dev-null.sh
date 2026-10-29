<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListApiOrganizationMentionsRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use App\Queries\OrganizationMentionsQuery;
use App\Queries\ReplyPreviewsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class OrganizationMentionController
{
    /**
     * A signed request gets the organization's Mentions tab as the web shows it to that user.
     */
    public function index(ListApiOrganizationMentionsRequest $request, #[CurrentUser] ?User $currentUser, OrganizationMentionsQuery $mentions, ReplyPreviewsQuery $replyPreviews): AnonymousResourceCollection
    {
        $organization = $request->organization();
        assert($organization instanceof Profile);

        if ($currentUser instanceof User) {
            $signedCommits = $mentions->builder($organization, $currentUser)->cursorPaginate(20);

            $replyPreviews->load($signedCommits->items(), $currentUser);

            return CommitResource::collection($signedCommits);
        }

        return CommitResource::collection(Commit::query()
            ->mentioning($organization)
            ->approved()
            ->withPublicPreview()
            ->latest()
            ->latest('id')
            ->cursorPaginate(20));
    }
}
