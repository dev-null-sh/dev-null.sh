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
        $rows = DB::table('repository_reactions')->where('user_id', $user->id)->where('commit_id', $commit->id)->orderByDesc('updated_at')->get();
        $reactions = [];
        $timestamps = [];
        foreach (DB::table('commit_reactions')->where('user_id', $user->id)->where('commit_id', $commit->id)->orderByDesc('updated_at')->get() as $row) {
            $emoji = $row->flavor;
            assert(is_string($emoji));
            $timestamps[$emoji] = $row->updated_at;
            $reactions[$emoji] = ['emoji' => $emoji, 'public' => true, 'repositories' => []];
        }
        foreach ($rows as $row) {
            assert(is_string($row->flavor));
            $emoji = \App\Services\ReactionEmoji::normalize(Crypt::decryptString($row->flavor));
            $reactions[$emoji] ??= ['emoji' => $emoji, 'public' => false, 'repositories' => []];
            $reactions[$emoji]['repositories'][] = $row->repository_mirror_id;
            $timestamps[$emoji] = max($timestamps[$emoji] ?? '', $row->updated_at);
        }

        return response()->json([
            'public' => DB::table('stars')->where('user_id', $user->id)->where('commit_id', $commit->id)->exists(),
            'reactions' => collect($reactions)->sortByDesc(fn (array $reaction) => $timestamps[$reaction['emoji']])->values(),
            'repositories' => RepositoryMirror::query()->where('user_id', $user->id)->orderBy('repository')->get()->map(function (RepositoryMirror $mirror) use ($rows): array {
                $flavor = $rows->firstWhere('repository_mirror_id', $mirror->id)?->flavor;

                return [...$mirror->summary(), 'selected' => is_string($flavor), 'flavor' => is_string($flavor) ? Crypt::decryptString($flavor) : null];
            }),
        ])->header('Cache-Control', 'private, no-store');
    }
}
