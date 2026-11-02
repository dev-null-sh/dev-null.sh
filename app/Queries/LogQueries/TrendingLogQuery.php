<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Services\TheAlgorithm;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Config;

final readonly class TrendingLogQuery implements LogQuery
{
    public function __construct(private TheAlgorithm $theAlgorithm)
    {
        //
    }

    /**
     * Ranked by the hot score, adjusted for author diversity and the viewer's network, and drawn at random, so the log
     * does not show the same commits in the same order every time it is opened. The draw is a joined subquery, so the
     * cursor pages on plain columns rather than on expressions.
     *
     * The seed of the draw is the first column the log is ordered by. Every commit of a draw shares it, so it does not
     * change the order, but it puts the seed in the cursor: the pages that follow, on the web and over the API, read it
     * back and keep the same draw, so they do not overlap.
     */
    public function builder(?User $user): CommitBuilder
    {
        $seed = $this->seed();

        $draw = $this->theAlgorithm
            ->draw($user, $seed, Config::float('trending.temperature'))
            ->selectRaw('?::bigint as trending_seed', [$seed]);

        return Commit::query()
            ->joinSub($draw, 'draw', 'draw.drawn_id', '=', 'commits.id')
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->addSelect(['draw.trending_seed', 'draw.trending_score'])
            ->orderByDesc('trending_seed')
            ->orderByDesc('trending_score')
            ->orderByDesc('id');
    }

    /**
     * The seed of the cursor being followed, or a new one when the log is opened or refreshed.
     */
    private function seed(): int
    {
        $seed = CursorPaginator::resolveCurrentCursor()?->toArray()['trending_seed'] ?? null;

        return is_int($seed) ? $seed : random_int(0, PHP_INT_MAX);
    }
}
