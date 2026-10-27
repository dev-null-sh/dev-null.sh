<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class RelatedCommitsQuery
{
    /**
     * Cosine distance up to which two text-embedding-3-small vectors say about the same thing. Measured on 281 real
     * posts: pairs under 0.45 are near duplicates, 0.45 to 0.60 share a topic, and past 0.60 the pairs come apart.
     */
    public const float MAX_DISTANCE = 0.60;

    public const int LIMIT = 3;

    /**
     * The top-level commits of other threads closest in meaning to the commit, closest first, loaded as the feeds load
     * them so the same card renders them. Only commits the viewer could find through search are suggested, and none
     * further than MAX_DISTANCE, so a commit with nothing close, or without an embedding, gets an empty list.
     *
     * The HNSW index hands back a fixed number of candidates before the filters run, so closer replies or hidden
     * commits could use them all up. An iterative scan keeps reading the index until LIMIT commits pass the filters.
     * The cutoff is applied to those afterwards, or a commit with nothing close would scan the whole index.
     *
     * @return Collection<int, Commit>
     */
    public function get(Commit $commit, ?User $viewer): Collection
    {
        $nearest = Commit::query()
            ->select('commits.id')
            ->selectRaw(
                'commits.embedding <=> (select source.embedding from commits as source where source.id = ?) as distance',
                [$commit->id],
            )
            ->topLevel()
            ->whereKeyNot($commit->threadRootId())
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->orderBy('distance')
            ->limit(self::LIMIT);

        return DB::transaction(function () use ($nearest, $viewer): Collection {
            DB::statement('set local hnsw.iterative_scan = strict_order');

            return Commit::query()
                ->joinSub($nearest, 'nearest', 'nearest.id', '=', 'commits.id')
                ->where('nearest.distance', '<=', self::MAX_DISTANCE)
                ->withVisibleReplyCount($viewer)
                ->withContinuationId($viewer)
                ->withCardDataFor($viewer)
                ->orderBy('nearest.distance')
                ->get()
                ->toBase();
        });
    }
}
