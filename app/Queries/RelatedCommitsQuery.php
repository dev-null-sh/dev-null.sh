<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class RelatedCommitsQuery
{
    /**
     * Cosine distance up to which two text-embedding-3-small vectors say about the same thing. Measured on 281 real
     * posts: pairs under 0.45 are near duplicates, 0.45 to 0.60 share a topic, and past 0.60 the pairs come apart.
     */
    public const float MAX_DISTANCE = 0.60;

    /**
     * Far under the HNSW index's default ef_search of 40, so filtering its candidates by visibility rarely starves the rail.
     */
    public const int LIMIT = 3;

    /**
     * The top-level commits of other threads closest in meaning to the commit, closest first, loaded as the feeds load
     * them so the same card renders them. Only commits the viewer could find through search are suggested, and none
     * further than MAX_DISTANCE, so a commit with nothing close, or without an embedding, gets an empty list.
     *
     * @return Collection<int, Commit>
     */
    public function get(Commit $commit, ?User $viewer): Collection
    {
        $distance = 'commits.embedding <=> (select source.embedding from commits as source where source.id = ?)';

        return Commit::query()
            ->topLevel()
            ->whereKeyNot($commit->threadRootId())
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->whereRaw("({$distance}) <= ?", [$commit->id, self::MAX_DISTANCE])
            ->withVisibleReplyCount($viewer)
            ->withContinuationId($viewer)
            ->withCardDataFor($viewer)
            ->orderByRaw($distance, [$commit->id])
            ->limit(self::LIMIT)
            ->get()
            ->toBase();
    }
}
