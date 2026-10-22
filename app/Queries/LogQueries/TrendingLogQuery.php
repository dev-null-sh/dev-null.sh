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
    private const string SEED = 'trending_seed';

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
        // cursor pages on a plain column rather than on an expression.
        $draw = DB::table('commits')
            ->select('id as drawn_id')
            ->selectRaw(
                'round((hot_score - ? * ln(-ln(((abs(hashtextextended(id::text, ?) % 1000000) + 0.5) / 1000000)::float8)))::numeric, 6) as trending_score',
                [Config::float('trending.temperature') / M_LN2, $this->seed()],
            );

        return Commit::query()
            ->joinSub($draw, 'draw', 'draw.drawn_id', '=', 'commits.id')
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->topLevel()
            ->withVisibleReplyCount($user)
            ->withReplyPreviews($user)
            ->withCardDataFor($user)
            ->addSelect('draw.trending_score')
            ->orderByDesc('trending_score')
            ->orderByDesc('id');
    }

    /**
     * Opening the log draws a new seed, kept in the session for the pages that follow as it scrolls, so they do not
     * overlap. Without a session, as over the API, the draw is always the same.
     */
    private function seed(): int
    {
        if (! $this->request->hasSession()) {
            return 0;
        }

        $seed = $this->request->session()->get(self::SEED);

        if (! is_int($seed) || ! CursorPaginator::resolveCurrentCursor() instanceof Cursor) {
            $seed = random_int(0, PHP_INT_MAX);

            $this->request->session()->put(self::SEED, $seed);
        }

        return $seed;
    }
}
