<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteCommitStarRequest;
use App\Models\Commit;
use App\Models\RepositoryMirror;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Only the picker for the signed-in viewer reads this. Repository-only reactions never enter feed queries. */
final readonly class CommitReactionStateController
{
    public function __invoke(DeleteCommitStarRequest $request, #[CurrentUser] User $user, Commit $commit): JsonResponse
    {
        $reactions = DB::table('repository_reactions')->where('user_id', $user->id)->where('commit_id', $commit->id)->pluck('flavor', 'repository_mirror_id');
        $public = DB::table('stars')->where('user_id', $user->id)->where('commit_id', $commit->id)->exists();

        return response()->json([
            'public' => $public,
            'repositories' => RepositoryMirror::query()->where('user_id', $user->id)->orderBy('repository')->get()->map(function (RepositoryMirror $mirror) use ($reactions): array {
                $flavor = $reactions->get($mirror->id);

                return [...$mirror->summary(), 'selected' => is_string($flavor), 'flavor' => is_string($flavor) ? Crypt::decryptString($flavor) : null];
            }),
        ])->header('Cache-Control', 'private, no-store');
    }
}
