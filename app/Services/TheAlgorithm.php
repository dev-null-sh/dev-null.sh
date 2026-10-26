<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\ThreadEngagement;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitBuilder;
use Carbon\CarbonInterface;

final readonly class TheAlgorithm
{
    /**
     * How much the log reshuffles on each visit, read through config('trending.temperature') so tests can set it to 0
     * for a fixed order. On production data, at 1.0, two visits share about 3 of their first 20 commits, and about 1 in
     * 8 of the commits shown is over a day old; at 0.5 they shared 13 of 20 and none was over 15 hours old.
     */
    public const float TEMPERATURE = 1.0;

    /**
     * A commit needs twice the engagement to rank with one committed this many seconds later.
     */
    private const int DOUBLING_PERIOD = 6 * 60 * 60;

    private const float STAR_WEIGHT = 0.5;

    private const float QUOTE_WEIGHT = 1.0;

    private const float REPLY_WEIGHT = 13.5;

    private const float REPLY_ANSWERED_BY_AUTHOR_WEIGHT = 75.0;

    private const float AUTHOR_DIVERSITY_DECAY = 0.5;

    private const float AUTHOR_DIVERSITY_FLOOR = 0.25;

    private const float OUT_OF_NETWORK_SCALE = 0.75;

    /**
     * Only commits this recent are drawn. A week-old commit is 28 doublings behind a new one, so it would not reach
     * the first pages anyway, and the draw reads one week of commits rather than every commit ever made.
     */
    private const int CANDIDATE_MAX_AGE_DAYS = 7;

    /**
     * The score of a thread, stored on its top-level commit and recounted whenever its engagement changes.
     *
     * 1. Each kind of engagement is weighed by how much it says about the thread:
     *
     *    | Engagement                                   | Weight | Worth, in stars |
     *    |----------------------------------------------|--------|-----------------|
     *    | A developer starring any of its commits      |    0.5 |               1 |
     *    | A developer quoting any of its commits       |    1.0 |               2 |
     *    | A developer replying anywhere in it          |   13.5 |              27 |
     *    | A developer replying whom the author answers |   75.0 |  150 more (177) |
     *
     *    Replies weigh the most, and a reply the author answers the most of all, because a conversation is what makes a
     *    thread worth reading. Reports do not count: moderation handles them, and a score that sank on a report would
     *    let one developer bury any commit.
     *
     * 2. The weighted sum counts on a log scale, in doublings: log2(1 + sum). The first stars lift a commit more than
     *    later ones do, and a commit with no engagement scores 0.
     *
     * 3. Time adds one doubling every six hours, so a commit needs twice the engagement to rank with one committed
     *    six hours later. The score grows with time rather than decaying with age, so it only changes when
     *    engagement does and can be stored and indexed.
     *
     * 4. The score is rounded so that it reads back from the database exactly as it was written, which the log's
     *    cursor relies on.
     */
    public function score(CarbonInterface $createdAt, ThreadEngagement $engagement): float
    {
        $weightedEngagement = self::STAR_WEIGHT * $engagement->stargazers
            + self::QUOTE_WEIGHT * $engagement->quoters
            + self::REPLY_WEIGHT * $engagement->repliers
            + self::REPLY_ANSWERED_BY_AUTHOR_WEIGHT * $engagement->repliersAnsweredByAuthor;

        return round(log(1 + $weightedEngagement, 2) + $createdAt->getTimestamp() / self::DOUBLING_PERIOD, 6);
    }

    /**
     * The top-level commits of the last seven days the viewer can see, each with its trending score: the stored score, adjusted for the log
     * and for the viewer, plus random noise, so the log does not show the same commits in the same order every time
     * it is opened. Every adjustment multiplies the weight of a commit, and the score counts doublings, so each one
     * adds log2 of its factor.
     *
     * 1. Author diversity: an author's commits are ranked by their stored score, and each one after the first is worth
     *    half the one before, down to a quarter: (1 - 0.25) * 0.5^position + 0.25, which gives 1, 0.625, 0.4375,
     *    0.34375 and so on. One prolific author cannot fill the log, but their best commit keeps its full score.
     *    Commits without an author keep their score.
     *
     * 2. Network: for a signed-in viewer, commits by people they do not follow are worth three quarters, about 2.5
     *    hours of age, so the people they chose rise a little without the log turning into their Following log. Their
     *    own commits count as followed. Guests follow no one, so nothing is lowered for them.
     *
     * 3. The draw (Gumbel-max trick): adding Gumbel noise, -ln(-ln(u)) for u uniform in (0, 1), to the logarithm of
     *    each commit's weight and sorting by the sum draws the commits at random, each with a chance of coming first
     *    proportional to its weight. The score counts doublings, so the weight is 2^(score / temperature): at 1, a
     *    commit with twice the engagement of another ranks above it 2 times out of 3, and at 0 the order is the
     *    score's. The higher the temperature, the more each visit differs and the further back in time it reaches. u comes from hashing the id with the draw's seed, so every page of a draw sorts the same way.
     *
     * 4. The sum is rounded so that it reads back exactly, which the cursor relies on.
     */
    public function draw(?User $viewer, int $seed, float $temperature): CommitBuilder
    {
        // log2((1 - floor) * decay^position + floor), where the position counts from 0 within the author's commits.
        $authorDiversity = 'case when commits.user_id is null then 0 else ln((1 - ?::float8) * power(?::float8, row_number() over (partition by commits.user_id order by commits.hot_score desc, commits.id desc) - 1) + ?::float8) / ln(2) end';

        // 0 for the viewer's own commits and the people they follow, log2(0.75) for everyone else.
        [$network, $networkBindings] = $viewer instanceof User
            ? ['case when commits.user_id = ? or exists (select 1 from follows where follows.follower_id = ? and follows.followed_id = commits.user_id) then 0 else ?::float8 end', [$viewer->id, $viewer->id, log(self::OUT_OF_NETWORK_SCALE, 2)]]
            : ['0', []];

        // temperature / ln(2) * ln(-ln(u)), subtracted below, adds the Gumbel noise on the scale of the doublings.
        $gumbelNoise = '?::float8 * ln(-ln(((abs(hashtextextended(commits.id::text, ?) % 1000000) + 0.5) / 1000000)::float8))';

        return Commit::query()
            ->topLevel()
            ->where('commits.created_at', '>=', now()->subDays(self::CANDIDATE_MAX_AGE_DAYS))
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->select('commits.id as drawn_id')
            ->selectRaw(
                "round((commits.hot_score + {$authorDiversity} + {$network} - {$gumbelNoise})::numeric, 6) as trending_score",
                [
                    self::AUTHOR_DIVERSITY_FLOOR, self::AUTHOR_DIVERSITY_DECAY, self::AUTHOR_DIVERSITY_FLOOR,
                    ...$networkBindings,
                    $temperature / M_LN2, $seed,
                ],
            );
    }
}
