<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Feature;
use App\Enums\MergeConflictSide;
use App\Models\Commit;
use App\Models\MergeConflict;
use App\Models\User;
use App\Services\Features;
use Illuminate\Support\Str;

final readonly class MergeConflictQuery
{
    private const int ExcerptLength = 280;

    public function __construct(private Features $features)
    {
        //
    }

    /**
     * The merge conflict in the replies to a commit, as long as the reader can see both sides. Like a poll, the votes
     * are only counted for those who picked a side, the two authors, and everyone once voting has closed.
     *
     * @return array{id: string, closes_at: string, is_closed: bool, side: string|null, can_vote: bool, ours: array{id: string, body: string, user: User|null, votes: int|null}, theirs: array{id: string, body: string, user: User|null, votes: int|null}}|null
     */
    public function get(Commit $commit, ?User $viewer): ?array
    {
        $mergeConflict = $this->features->enabled(Feature::MergeConflicts) ? $commit->mergeConflict()->first() : null;

        if (! $mergeConflict instanceof MergeConflict) {
            return null;
        }

        $sides = Commit::query()
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->whereKey([$mergeConflict->ours_id, $mergeConflict->theirs_id])
            ->with('user')
            ->get()
            ->keyBy('id');

        $ours = $sides->get($mergeConflict->ours_id);
        $theirs = $sides->get($mergeConflict->theirs_id);

        if (! $ours instanceof Commit || ! $theirs instanceof Commit) {
            return null;
        }

        $mergeConflict->setRelation('ours', $ours)->setRelation('theirs', $theirs);

        $side = $viewer instanceof User ? $mergeConflict->sideOf($viewer) : null;
        $isAuthor = $viewer instanceof User && in_array($viewer->id, [$ours->user_id, $theirs->user_id], true);
        $counts = $side instanceof MergeConflictSide || $isAuthor || $mergeConflict->isClosed()
            ? $mergeConflict->votes()->toBase()->selectRaw('side, count(*) as votes')->groupBy('side')->pluck('votes', 'side')
            : null;

        return [
            'id' => $mergeConflict->id,
            'closes_at' => $mergeConflict->closes_at->toIso8601String(),
            'is_closed' => $mergeConflict->isClosed(),
            'side' => $side?->value,
            'can_vote' => $viewer instanceof User && ! $side instanceof MergeConflictSide && $mergeConflict->canBeVotedOnBy($viewer),
            'ours' => $this->side($ours, $counts?->get(MergeConflictSide::Ours->value, 0)),
            'theirs' => $this->side($theirs, $counts?->get(MergeConflictSide::Theirs->value, 0)),
        ];
    }

    /**
     * @return array{id: string, body: string, user: User|null, votes: int|null}
     */
    private function side(Commit $reply, mixed $votes): array
    {
        return [
            'id' => $reply->id,
            'body' => Str::limit($reply->body, self::ExcerptLength),
            'user' => $reply->user,
            'votes' => is_numeric($votes) ? (int) $votes : null,
        ];
    }
}
