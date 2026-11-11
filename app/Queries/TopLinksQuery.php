<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The links that the most people used as their link card lately, as topics to explore, each with its most starred
 * commits. Only the ranking looks at the last 30 days: the commits shown and counted are all the ones the viewer may
 * see, as on the link's own page.
 *
 * @phpstan-type TopLinkRow object{id: string, url: string, title: string|null, description: string|null, site_name: string|null, people: int}
 * @phpstan-type TopLink array{url: string, title: string|null, description: string|null, site_name: string|null, people: int, commits: int, stars: int, top_commits: list<Commit>}
 */
final readonly class TopLinksQuery
{
    private const int DAYS = 30;

    private const int LIMIT = 10;

    public function __construct(private LinkCommitsQuery $commits)
    {
        //
    }

    /**
     * @return list<TopLink>
     */
    public function get(User $user): array
    {
        $counts = Commit::query()
            ->approved()
            ->whereNotNull('link_preview_id')
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->toBase()
            ->select('link_preview_id')
            ->selectRaw('count(distinct user_id) as people, count(*) as commits, max(created_at) as last_mentioned_at')
            ->groupBy('link_preview_id');

        /** @var list<TopLinkRow> $rows */
        $rows = DB::query()
            ->fromSub($counts, 'counts')
            ->join('link_previews', 'link_previews.id', '=', 'counts.link_preview_id')
            ->orderByDesc('people')->orderByDesc('commits')->latest('last_mentioned_at')
            ->orderBy('link_previews.id')
            ->limit(self::LIMIT)
            ->get(['link_previews.id', 'link_previews.url', 'link_previews.title', 'link_previews.description', 'link_previews.site_name', 'counts.people'])
            ->all();

        $linkIds = array_map(fn (object $row): string => $row->id, $rows);
        $top = $this->commits->top($linkIds, $user);
        $stars = $this->commits->totalStarCounts($linkIds);
        $visibleCommits = $this->commits->visibleCounts($linkIds, $user);

        return array_map(fn (object $row): array => [
            'url' => $row->url,
            'title' => $row->title,
            'description' => $row->description,
            'site_name' => $row->site_name,
            'people' => $row->people,
            'commits' => $visibleCommits[$row->id] ?? 0,
            'stars' => $stars[$row->id] ?? 0,
            'top_commits' => $top[$row->id] ?? [],
        ], $rows);
    }
}
