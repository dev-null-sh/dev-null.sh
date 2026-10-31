<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Services\TheAlgorithm;
use Illuminate\Pagination\Cursor;
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
     * back and keep the same draw, so they do not overlap. The time from which the viewer's own commits are pinned is
     * kept the same way, so a commit that ages out of the pin while the log scrolls does not move back into a page. A
     * cursor issued before that was kept has no such time, so it is followed without it rather than failing to page.
     */
    public function builder(?User $user): CommitBuilder
    {
        $seed = $this->cursorInteger('trending_seed') ?? random_int(0, PHP_INT_MAX);
        $pinnedSince = $this->cursorInteger('trending_pinned_since') ?? $this->theAlgorithm->pinnedSince();

        $draw = $this->theAlgorithm
            ->draw($user, $seed, Config::float('trending.temperature'), $pinnedSince)
            ->selectRaw('?::bigint as trending_seed', [$seed])
            ->selectRaw('?::bigint as trending_pinned_since', [$pinnedSince]);

        return Commit::query()
            ->joinSub($draw, 'draw', 'draw.drawn_id', '=', 'commits.id')
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->addSelect(['draw.trending_seed', 'draw.trending_pinned_since', 'draw.trending_score'])
            ->orderByDesc('trending_seed')
            ->unless($this->followsCursorWithoutPinnedSince(), fn (CommitBuilder $builder): CommitBuilder => $builder->orderByDesc('trending_pinned_since'))
            ->orderByDesc('trending_score')
            ->orderByDesc('id');
    }

    /**
     * Whether the cursor being followed was issued before the log kept the time from which own commits are pinned.
     */
    private function followsCursorWithoutPinnedSince(): bool
    {
        return CursorPaginator::resolveCurrentCursor() instanceof Cursor && $this->cursorInteger('trending_pinned_since') === null;
    }

    /**
     * A value of the draw that the cursor being followed carries, or null when the log is opened or refreshed.
     */
    private function cursorInteger(string $column): ?int
    {
        $value = CursorPaginator::resolveCurrentCursor()?->toArray()[$column] ?? null;

        return is_int($value) ? $value : null;
    }
}
