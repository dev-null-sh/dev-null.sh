<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateCommit;
use App\Actions\DeleteCommit;
use App\Http\Requests\CreateApiCommitRequest;
use App\Http\Requests\DeleteApiCommitRequest;
use App\Http\Requests\ShowApiCommitRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class CommitController
{
    /**
     * A signed request gets the commit as its page shows it to that user, with the commits it replies to above it, and
     * who else a reply to it can go to, as the web's Replying to lists them: everyone it was addressed to, besides its
     * author and the user.
     */
    public function show(ShowApiCommitRequest $request, #[CurrentUser] ?User $currentUser, Commit $commit, CommitPageQuery $page, CommitAncestorsQuery $ancestors): CommitResource
    {
        if ($currentUser instanceof User) {
            $commit = $page->get($commit, $currentUser);

            return CommitResource::make($commit)->additional([
                'ancestors' => CommitResource::collection($ancestors->get($commit, $currentUser)),
                'reply_participants' => collect($commit->mentions ?? [])
                    ->reject(fn (string $id): bool => in_array($id, [$currentUser->id, $commit->author_id], true))
                    ->map(fn (string $id, int|string $handle): array => ['id' => $id, 'handle' => (string) $handle])
                    ->values(),
            ]);
        }

        return CommitResource::make(Commit::query()
            ->withPublicPreview()
            ->whereKey($commit->id)
            ->sole());
    }

    /**
     * Answers with the new commit as its page shows it, with the commit it quotes and its poll, pending review unless
     * the user is an admin.
     */
    public function store(CreateApiCommitRequest $request, #[CurrentUser] User $user, CreateCommit $action, CommitPageQuery $page): JsonResponse
    {
        $commit = $action->handle($user, $request->string('body')->value(), $request->parent(), $request->quotedCommit(), $request->images(), $request->imageDescriptions(), poll: $request->poll(), replyRecipients: $request->replyRecipients());

        return CommitResource::make($page->get($commit, $user))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(DeleteApiCommitRequest $request, Commit $commit, DeleteCommit $action): Response
    {
        $action->handle($commit);

        return response()->noContent();
    }
}
