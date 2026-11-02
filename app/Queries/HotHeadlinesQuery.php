<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dto\ThreadEngagement;
use App\Models\Commit;
use App\Models\User;
use App\Services\TheAlgorithm;
use Illuminate\Support\Facades\DB;

final readonly class HotHeadlinesQuery
{
    public function __construct(private TheAlgorithm $theAlgorithm)
    {
        //
    }

    /**
     * Each author contributes only their strongest recent commit per headline: one, plus its positive hot-score lead
     * over a new, unengaged commit. That lead already measures engagement logarithmically and discounts age, so one
     * viral thread does not contribute its raw engagement count. The eight-hour window uses posting time, never the
     * time a backfill linked the headline.
     * Ties use author breadth and name, never post count or recency, so repeated weaker posts cannot lift a headline.
     * Rankings are deterministic, without the Trending log's random draw or network adjustments.
     *
     * @return list<array{id: string, name: string, commits_count: int, score: float}>
     */
    public function get(?User $viewer = null): array
    {
        $now = now();
        $baseline = $this->theAlgorithm->score($now, ThreadEngagement::none());

        $contributions = Commit::query()
            ->topLevel()
            ->approved()
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->whereBetween('commits.created_at', [$now->copy()->subHours(8), $now])
            ->toBase()
            ->join('commit_headline', 'commit_headline.commit_id', '=', 'commits.id')
            ->select('commit_headline.headline_id')
            ->selectRaw('max(commits.hot_score) as hot_score, count(*) as commits_count')
            ->groupBy('commit_headline.headline_id', 'commits.author_id');

        /** @var list<object{id: string, name: string, commits_count: int, score: float}> $rows */
        $rows = DB::query()
            ->fromSub($contributions, 'contributions')
            ->join('headlines', 'headlines.id', '=', 'contributions.headline_id')
            ->select('headlines.id', 'headlines.name')
            ->selectRaw('sum(contributions.commits_count)::bigint as commits_count, count(*) as authors_count, sum(1 + greatest(0::float8, contributions.hot_score - ?::float8)) as score', [$baseline])
            ->groupBy('headlines.id', 'headlines.name')
            ->orderByDesc('score')
            ->orderByDesc('authors_count')
            ->orderBy('headlines.name')
            ->limit(5)
            ->get()->all();

        return array_map(fn (object $row): array => [
            'id' => $row->id,
            'name' => $row->name,
            'commits_count' => $row->commits_count,
            'score' => $row->score,
        ], $rows);
    }
}
