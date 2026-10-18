<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dto\ThreadContinuation;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class CommitContinuationQuery
{
    public const int PER_PAGE = 10;

    public const int MAX_LENGTH = 100;

    private const int MAX_POSITION = 1000;

    /**
     * Walks down the author's earliest replies whoever the viewer is; the chain is cut at the first one they may not see.
     */
    private const string CHAIN = <<<'SQL'
        WITH RECURSIVE chain AS (
            SELECT CAST(? AS uuid) AS id, 0 AS depth
            UNION ALL
            SELECT step.id, chain.depth + 1 FROM chain
            CROSS JOIN LATERAL (
                SELECT next.id FROM commits next
                WHERE next.parent_id = chain.id AND next.user_id = ? AND next.deleted_at IS NULL
                ORDER BY next.created_at, next.id
                LIMIT 1
            ) step
            WHERE chain.depth < ?
        )
        SELECT id FROM chain WHERE depth > 0
        SQL;

    /**
     * Walks up while each commit is its author's earliest reply to the one above, whoever the viewer is.
     */
    private const string POSITION = <<<'SQL'
        WITH RECURSIVE up AS (
            SELECT CAST(? AS uuid) AS id, CAST(? AS uuid) AS parent_id, 1 AS depth
            UNION ALL
            SELECT parent.id, parent.parent_id, up.depth + 1 FROM up
            JOIN commits parent ON parent.id = up.parent_id
            WHERE parent.user_id = ? AND parent.deleted_at IS NULL AND up.depth < ? AND (
                SELECT next.id FROM commits next
                WHERE next.parent_id = parent.id AND next.user_id = ? AND next.deleted_at IS NULL
                ORDER BY next.created_at, next.id
                LIMIT 1
            ) = up.id
        )
        SELECT id FROM up WHERE depth > 1
        SQL;

    /**
     * Follows the author's earliest reply to themselves at each step, so a thread they wrote as replies reads in order.
     */
    public function get(Commit $commit, ?User $user): ThreadContinuation
    {
        $hasNoNext = $commit->hasAttribute('continuation_id') && $commit->continuation_id === null;

        if ($commit->user_id === null || ($hasNoNext && $commit->parent_id === null)) {
            return new ThreadContinuation(commits: new Collection, position: 1, length: 0, isCapped: false, nextId: null);
        }

        $ids = $hasNoNext ? [] : $this->chainBelow($commit, $user);
        $isCapped = count($ids) > self::MAX_LENGTH;
        $ids = array_slice($ids, 0, self::MAX_LENGTH);
        $shown = array_slice($ids, 0, self::PER_PAGE);

        $cards = $shown === [] ? new Collection : Commit::query()
            ->whereKey($shown)
            ->withVisibleReplyCount($user)
            ->withCardDataFor($user)
            ->get()
            ->keyBy('id');

        $commits = new Collection;

        foreach ($shown as $index => $id) {
            $card = $cards->get($id);

            if ($card instanceof Commit) {
                $card->setAttribute('replies_count', isset($ids[$index + 1]) ? $card->replies_count - 1 : $card->replies_count);
                $commits->push($card);
            }
        }

        return new ThreadContinuation(
            commits: $commits,
            position: $this->positionOf($commit, $user),
            length: count($ids),
            isCapped: $isCapped,
            nextId: $ids[self::PER_PAGE] ?? null,
        );
    }

    /**
     * @return list<string>
     */
    private function chainBelow(Commit $commit, ?User $user): array
    {
        $byParent = Commit::query()
            ->whereRaw('commits.id IN ('.self::CHAIN.')', [$commit->id, $commit->user_id, self::MAX_LENGTH + 1])
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->get(['id', 'parent_id'])
            ->keyBy('parent_id');

        $ids = [];
        $parentId = $commit->id;

        while (($next = $byParent->get($parentId)) instanceof Commit) {
            $ids[] = $parentId = $next->id;
        }

        return $ids;
    }

    private function positionOf(Commit $commit, ?User $user): int
    {
        if ($commit->parent_id === null) {
            return 1;
        }

        $byId = Commit::query()
            ->whereRaw('commits.id IN ('.self::POSITION.')', [$commit->id, $commit->parent_id, $commit->user_id, self::MAX_POSITION, $commit->user_id])
            ->visibleTo($user)
            ->withoutMutedAuthorsFor($user)
            ->get(['id', 'parent_id'])
            ->keyBy('id');

        $position = 1;
        $parentId = $commit->parent_id;

        while (($parent = $byId->get($parentId)) instanceof Commit) {
            $position++;
            $parentId = $parent->parent_id;
        }

        return $position;
    }
}
