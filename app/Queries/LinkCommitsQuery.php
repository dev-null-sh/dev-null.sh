<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The commits whose link card is the given link, the link being their topic.
 */
final readonly class LinkCommitsQuery
{
    private const int TOP_COMMITS = 3;

    /**
     * Every commit of the topic, the most starred first, then the newest, each standing on its own like in a log.
     */
    public function builder(LinkPreview $link, User $user): CommitBuilder
    {
        return $this->withCardData(Commit::query(), $user)
            ->where('link_preview_id', $link->id)
            ->visibleTo($user)
            ->withReplyPreviews($user)
            ->orderByDesc('stars_count')
            ->latest('commits.created_at')
            ->orderByDesc('commits.id');
    }

    /**
     * The most starred commits of each topic, then its newest ones, keyed by link. They are ranked in a joined subquery,
     * among the commits the viewer may see.
     *
     * @param  list<string>  $linkIds
     * @return array<string, list<Commit>>
     */
    public function top(array $linkIds, User $user): array
    {
        $stars = (new Commit)->stars()->newPivotStatement()
            ->select('commit_id')
            ->selectRaw('count(*) as stars')
            ->whereIn('commit_id', Commit::query()->whereIn('link_preview_id', $linkIds)->select('id'))
            ->groupBy('commit_id');

        $ranked = Commit::query()
            ->leftJoinSub($stars, 'commit_stars', 'commit_stars.commit_id', '=', 'commits.id')
            ->whereIn('link_preview_id', $linkIds)
            ->visibleTo($user)
            ->select('commits.id as ranked_id')
            ->selectRaw('row_number() over (partition by commits.link_preview_id order by coalesce(commit_stars.stars, 0) desc, commits.created_at desc, commits.id desc) as topic_rank');

        /** @var Collection<int, Commit> $commits */
        $commits = $this->withCardData(Commit::query(), $user)
            ->joinSub($ranked, 'ranked', 'ranked.ranked_id', '=', 'commits.id')
            ->where('ranked.topic_rank', '<=', self::TOP_COMMITS)
            ->orderBy('ranked.topic_rank')
            ->get();

        $top = [];

        foreach ($commits as $commit) {
            $top[(string) $commit->link_preview_id][] = $commit;
        }

        return $top;
    }

    /**
     * How many commits of each link the viewer may see, however old, keyed by link.
     *
     * @param  list<string>  $linkIds
     * @return array<string, int>
     */
    public function visibleCounts(array $linkIds, User $user): array
    {
        /** @var list<object{link_preview_id: string, commits: int}> $rows */
        $rows = Commit::query()
            ->whereIn('link_preview_id', $linkIds)
            ->visibleTo($user)
            ->toBase()
            ->select('link_preview_id')
            ->selectRaw('count(*) as commits')
            ->groupBy('link_preview_id')
            ->get()
            ->all();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row->link_preview_id] = $row->commits;
        }

        return $counts;
    }

    /**
     * How many stars the approved commits of each link have, keyed by link.
     *
     * @param  list<string>  $linkIds
     * @return array<string, int>
     */
    public function totalStarCounts(array $linkIds): array
    {
        $commits = Commit::query()->whereIn('link_preview_id', $linkIds)->approved()->toBase()->select('id', 'link_preview_id');

        /** @var list<object{link_preview_id: string, stars: int}> $rows */
        $rows = (new Commit)->stars()->newPivotStatement()
            ->joinSub($commits, 'link_commits', 'link_commits.id', '=', 'stars.commit_id')
            ->groupBy('link_commits.link_preview_id')
            ->select('link_commits.link_preview_id')
            ->selectRaw('count(*) as stars')
            ->get()
            ->all();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row->link_preview_id] = $row->stars;
        }

        return $counts;
    }

    /**
     * @return array{commits: int, people: int}
     */
    public function stats(LinkPreview $link): array
    {
        $commits = Commit::query()->where('link_preview_id', $link->id)->approved();

        return [
            'commits' => $commits->count(),
            'people' => $commits->clone()->distinct()->count('user_id'),
        ];
    }

    private function withCardData(CommitBuilder $commits, User $user): CommitBuilder
    {
        return $commits
            ->withVisibleReplyCount($user)
            ->withCardDataFor($user)
            ->with([
                'parent' => function (BelongsTo $parent) use ($user): void {
                    $parent->getQuery()->withTrashed()->visibleTo($user)->with('author');
                },
            ]);
    }
}
