<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateCommitPastel;
use App\Exceptions\ApiException;
use App\Http\Requests\CreateApiCommitPastelRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitPastelController
{
    /**
     * Gives the commit one pastel from the user's daily allowance, as the web's pastel button does. It answers with the
     * commit as its page shows it, so the app gets the new pasteis_count, with how many pastéis the user has left
     * today and how many its author has been given in all. With none left, it is a pasteis_exhausted error.
     */
    public function store(CreateApiCommitPastelRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitPastel $action, CommitPageQuery $page): CommitResource|JsonResponse
    {
        if (! $action->handle($user, $commit)) {
            return ApiException::pasteisExhausted()->render();
        }

        $commit = $page->get($commit, $user);

        return CommitResource::make($commit)->additional([
            'pasteis_remaining' => $user->refresh()->pasteisRemaining(),
            'pasteis_resets_at' => today()->addDay()->toIso8601ZuluString(),
            'author_pasteis_received' => $commit->user?->pasteis_received,
        ]);
    }
}
