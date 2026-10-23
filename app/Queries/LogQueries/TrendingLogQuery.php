<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final readonly class TrendingLogQuery implements LogQuery
{
    public function __construct(private Request $request)
    {
        //
    }

    /**
     * Ranked by hot score, so commits that draw stars and replies rise and then give way to newer ones, but drawn at
     * random, so the log does not show the same commits in the same order every time it is opened.
     */
    public function builder(?User $user): CommitBuilder
    {
        // Gumbel-max trick: adding Gumbel noise, -ln(-ln(u)) for u uniform in (0, 1), to the logarithm of each commit's
        // weight and sorting by the sum draws the commits at random, each with a chance of coming first proportional to
        // its weight. The hot score counts doublings, so the weight is 2^(hot_score / temperature): at 0.5, a commit
        // with twice the engagement of another ranks above it 4 times out of 5, and at 0 the order is the hot score's.
        // u comes from hashing the id with the draw's seed, so every page of a draw sorts the same way, and the sum is
        // rounded so that it reads back exactly, which the cursor relies on. It is drawn in a joined subquery, so the
        // cursor pages on a plain column rather than on an expression. The seed is a column too, ordered on first: it is
        // the same on every row, so it changes nothing in the order, but it rides along in the cursor to the next page.
        $seed = $this->seed();

        $draw = DB::table('commits')
            ->select('id as drawn_id')
            ->selectRaw('?::bigint as trending_seed', [$seed])
            ->selectRaw(
                'round((hot_score - ? * ln(-ln(((abs(hashtextextended(id::text, ?) % 1000000) + 0.5) / 1000000)::float8)))::numeric, 6) as trending_score',
                [Config::float('trending.temperature') / M_LN2, $seed],
            );

        return Commit::query()
            ->joinSub($draw, 'draw', 'draw.drawn_id', '=', 'commits.id')
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->addSelect(['draw.trending_seed', 'draw.trending_score'])
            ->orderByDesc('trending_seed')
            ->orderByDesc('trending_score')
            ->orderByDesc('id');
    }

    /**
     * Opening the log draws a new seed, and the pages that follow as it scrolls read it back from their cursor, so they
     * do not overlap. Unlike the session, the cursor belongs to one tab, so opening the log in another draws a seed of
     * its own without changing this one. Without a session, as over the API, the draw is always the same.
     */
    private function seed(): int
    {
        $cursor = CursorPaginator::resolveCurrentCursor();
        $seed = $cursor instanceof Cursor ? $cursor->toArray()['trending_seed'] ?? null : null;

        if (is_int($seed)) {
            return $seed;
        }

        return $this->request->hasSession() ? random_int(0, PHP_INT_MAX) : 0;
    }
}
