<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Log;
use App\Http\Requests\ShowLogRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\GuildCommitsQuery;
use App\Queries\LatestLogCommitsQuery;
use App\Queries\LogCommitsQuery;
use App\Queries\ReplyPreviewsQuery;
use App\Queries\SuggestedCommitsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class LogController
{
    /**
     * Following, Latest or Trending, as the web shows it, 20 commits a page. With ?since=, it counts the commits newer
     * than that one, the way the web does: none on Trending, which is ranked rather than newest first. An empty
     * Following log suggests a few commits, as on the web. On Following, each commit also says which joined guild
     * brought it there, or null, which is what allows display of the "because you joined" message.
     */
    public function show(ShowLogRequest $request, #[CurrentUser] User $user, Log $log, LatestLogCommitsQuery $latest, SuggestedCommitsQuery $suggested, ReplyPreviewsQuery $replyPreviews, GuildCommitsQuery $guilds): AnonymousResourceCollection
    {
        $since = $request->since();
        $commits = resolve(LogCommitsQuery::class)->paginate($log, $user, cursor: $request->cursor($log));

        $replyPreviews->load($commits->items(), $user);

        if ($log === Log::Following) {
            $joined = $guilds->joinedFor($commits->items(), $user);

            foreach ($commits->items() as $commit) {
                $commit->setAttribute('joined_guild', $joined[$commit->id] ?? null);
            }
        }

        return CommitResource::collection($commits)->additional([
            'log' => $log,
            'is_ranked' => $log->isRanked(),
            ...$since instanceof Commit ? ['new_commits_count' => $latest->count($log, $user, $since)] : [],
            'suggestions' => CommitResource::collection($log === Log::Following && ! $user->following()->exists()
                ? $suggested->get($user)
                : new Collection()),
        ]);
    }
}
