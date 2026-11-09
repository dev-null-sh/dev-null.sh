<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dto\EmbedOptions;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Profile;

/**
 * The commits an embed shows, as a guest sees them, each one either shown or left as a tombstone so the rest of the
 * thread still reads.
 *
 * @phpstan-type EmbedNode array{id: string, tombstone: 'deleted'|'unavailable'|null, commit: Commit|null, replies: list<mixed>, more: int}
 */
final class CommitEmbedQuery
{
    /**
     * The most commits one card holds, whatever depth it asks for.
     */
    public const int MAX_COMMITS = 40;

    private int $budget = self::MAX_COMMITS;

    public function __construct(
        private readonly CommitAncestorsQuery $ancestors,
        private readonly CommitContinuationQuery $continuation,
        private readonly CommitRepliesQuery $replies,
    ) {}

    /**
     * Why the card can't show a commit. An author who turned embeds off reads like any commit that isn't public: only
     * they see their own settings.
     *
     * @return 'deleted'|'unavailable'|null
     */
    public static function tombstone(Commit $commit): ?string
    {
        if ($commit->trashed()) {
            return 'deleted';
        }

        $author = $commit->author;
        $optsOut = $author instanceof Profile
            && ($author->user?->allows_embeds === false || $author->organization?->allows_embeds === false);

        return $commit->status !== CommitStatus::Approved || $optsOut ? 'unavailable' : null;
    }

    /**
     * @return array{ancestors: list<EmbedNode>, focal: EmbedNode, thread: list<EmbedNode>}
     */
    public function get(Commit $commit, EmbedOptions $options): array
    {
        $this->budget = self::MAX_COMMITS;

        $focal = Commit::query()
            ->withTrashed()
            ->withVisibleReplyCount(null)
            ->withContinuationId(null)
            ->withCardDataFor(null)
            ->findOrFail($commit->id);

        $thread = $options->thread && $focal->parent_id === null && self::tombstone($focal) === null
            ? $this->continuation->get($focal, null)->commits->all()
            : [];

        return [
            'ancestors' => array_values(array_map(fn (Commit $ancestor): array => $this->node($ancestor), $this->ancestors->get($focal, null)->all())),
            // The next commit of the thread reads below, on the trunk, so it isn't one of the replies too, like on the
            // commit's page.
            'focal' => $this->node($focal, $options, 0, $thread === [] ? null : $thread[0]->id),
            'thread' => array_values(array_map(fn (Commit $next): array => $this->node($next), $thread)),
        ];
    }

    /**
     * @return EmbedNode
     */
    private function node(Commit $commit, ?EmbedOptions $options = null, int $level = 0, ?string $except = null): array
    {
        $tombstone = self::tombstone($commit);
        // The reply count already leaves out the commit's continuation, which `withContinuationId` picks.
        $count = (int) ($commit->replies_count ?? 0);
        $replies = [];

        if ($options instanceof EmbedOptions && $options->replies > 0 && $level < $options->depth && $count > 0 && $this->budget > 0) {
            $children = $this->replies->paginate($commit, null, limit: min($options->replies, $this->budget), except: $except)->items();
            $this->budget -= count($children);

            foreach ($children as $child) {
                $replies[] = $this->node($child, $options, $level + 1);
            }
        }

        return [
            'id' => $commit->id,
            'tombstone' => $tombstone,
            'commit' => $tombstone === null ? $commit : null,
            'replies' => $replies,
            'more' => max(0, $count - count($replies)),
        ];
    }
}
