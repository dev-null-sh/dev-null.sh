<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The links that the most people used as their link card lately, to try the link page on real ones.
 *
 * @phpstan-type TopLinkRow object{url: string, title: string|null, site_name: string|null, people: int, commits: int, last_mentioned_at: string}
 * @phpstan-type TopLink array{url: string, title: string|null, site_name: string|null, people: int, commits: int, last_mentioned_at: string}
 */
final readonly class TopLinksQuery
{
    private const int DAYS = 30;

    private const int LIMIT = 10;

    /**
     * @return list<TopLink>
     */
    public function get(): array
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
            ->limit(self::LIMIT)
            ->get(['link_previews.url', 'link_previews.title', 'link_previews.site_name', 'counts.people', 'counts.commits', 'counts.last_mentioned_at'])
            ->all();

        return array_map(fn (object $row): array => [
            'url' => $row->url,
            'title' => $row->title,
            'site_name' => $row->site_name,
            'people' => $row->people,
            'commits' => $row->commits,
            'last_mentioned_at' => Date::parse($row->last_mentioned_at)->toIso8601String(),
        ], $rows);
    }
}
