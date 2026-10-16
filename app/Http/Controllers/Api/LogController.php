<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Log;
use App\Http\Requests\ShowLogRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LatestLogCommitsQuery;
use App\Queries\SuggestedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

final readonly class LogController
{
    /**
     * Following, Latest or Trending, as the web shows it, 20 commits a page. With ?since=, it counts the commits newer
     * than that one, the way the web does: none on Trending, which is ranked rather than newest first. An empty
     * Following log suggests a few commits, as on the web.
     */
    public function show(ShowLogRequest $request, #[CurrentUser] User $user, Log $log, LatestLogCommitsQuery $latest, SuggestedCommitsQuery $suggested): AnonymousResourceCollection
    {
        $since = $request->since();

        return CommitResource::collection($log->query()->builder($user)->cursorPaginate(20))->additional([
            'log' => $log,
            'is_ranked' => $log->isRanked(),
            ...$since instanceof Commit ? ['new_commits_count' => $latest->count($log, $user, $since)] : [],
            'suggestions' => CommitResource::collection($log === Log::Following && ! $user->following()->exists()
                ? $suggested->get($user)
                : new Collection()),
        ]);
    }
}
