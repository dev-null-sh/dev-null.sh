<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\ExploreTopic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use stdClass;

final readonly class ExploreBatchQuery
{
    /**
     * Keep the oldest remaining commit first and choose its nearest neighbours
     * only from the supplied cohort. Preserve cohort order for equal distances.
     * Missing or zero vectors compare at distance 2, including ties with opposite
     * vectors. A seed without a usable vector preserves chronological order.
     *
     * @param  Collection<int, Commit>  $waiting
     * @return Collection<int, Commit>
     */
    public function commits(Collection $waiting, bool $semantic = true): Collection
    {
        $seed = $waiting->first();
        $seedVector = $seed?->embedding;
        $limit = config()->integer('explore.request_size');
        if (! $semantic || ! $seed instanceof Commit || ! $this->usable($seedVector) || $limit <= 1) {
            return $waiting->take($limit)->values();
        }

        $candidates = $waiting->reject(fn (Commit $commit): bool => $commit->is($seed));
        if ($candidates->isEmpty()) {
            return $waiting->values();
        }

        // Bound the relation before sorting so an approximate global vector index
        // cannot under-fill this batch after filtering to the claimed IDs.
        $cohort = DB::table('commits')->whereIn('id', $candidates->pluck('id'))->select(['id', 'embedding'])->limit($candidates->count());
        $neighbors = DB::query()->fromSub($cohort, 'cohort')->select('id');
        if ($candidates->contains(fn (Commit $commit): bool => ! $this->usable($commit->embedding))) {
            // pgvector returns NULL for missing vectors and NaN for zero vectors.
            $neighbors->selectVectorDistance('embedding', $seedVector, as: 'distance');
            $neighbors = DB::query()->fromSub($neighbors, 'neighbors')->select('id')
                ->orderByRaw("coalesce(nullif(distance, 'NaN'::double precision), 2.0)");
        } else {
            $neighbors->orderByVectorDistance('embedding', $seedVector);
        }

        $ids = $neighbors->orderByRaw('array_position(?::uuid[], id)', ['{'.$candidates->pluck('id')->implode(',').'}'])
            ->limit($limit - 1)->get()->map(fn (stdClass $row): string => new Fluent((array) $row)->string('id')->value());
        $byId = $waiting->keyBy('id');

        return $ids->map(fn (string $id): Commit => $byId->only([$id])->sole())->prepend($seed)->values();
    }

    /**
     * Rank recent subjects and stories by the closest selected commit to their
     * average member embedding. PostgreSQL calculates all cosine distances.
     * Missing or zero vectors fall back to 2. Title matches retain their 0.1
     * boost and Unicode matching behavior; equal scores use stable topic IDs.
     * Apply the catalogue limit independently to subjects and stories.
     *
     * @param  Collection<int, Commit>  $commits
     * @return Collection<int, ExploreTopic>
     */
    public function topics(Collection $commits): Collection
    {
        $topics = ExploreTopic::query()->whereNull('suppressed_at')->where('updated_at', '>=', now()->subDays(7))->orderBy('id')->get();
        if ($topics->isEmpty()) {
            return $topics->toBase();
        }

        $vectors = [];
        foreach ($commits as $commit) {
            $vector = $commit->embedding;
            if ($this->usable($vector)) {
                $vectors[] = $vector;
            }
        }

        $distances = DB::table('explore_topics')->whereIn('explore_topics.id', $topics->modelKeys())->select('explore_topics.id');
        if ($vectors !== []) {
            $centroids = DB::table('explore_commit_topic as membership')->join('commits', 'commits.id', '=', 'membership.commit_id')
                ->whereIn('membership.topic_id', $topics->modelKeys())
                ->whereNull('commits.deleted_at')->where('commits.status', 'approved')
                ->where('commits.created_at', '>=', now()->subDays(7))->whereNotNull('commits.embedding')
                ->select('membership.topic_id')->selectRaw('avg(commits.embedding) as embedding')->groupBy('membership.topic_id');
            $distances->leftJoinSub($centroids, 'centroids', 'centroids.topic_id', '=', 'explore_topics.id');
            $comparisons = DB::query()->selectVectorDistance('centroids.embedding', $vectors[0], as: 'distance');
            foreach (array_slice($vectors, 1) as $vector) {
                $comparisons->unionAll(DB::query()->selectVectorDistance('centroids.embedding', $vector, as: 'distance'));
            }

            $distances->selectSub(DB::query()->fromSub($comparisons, 'comparisons')
                ->selectRaw("min(nullif(distance, 'NaN'::double precision))"), 'distance');
        } else {
            $distances->selectRaw('null::double precision as distance');
        }

        $score = 'coalesce(distance, 2.0)';
        $matches = $topics->filter(fn (ExploreTopic $topic): bool => $commits->contains(
            fn (Commit $commit): bool => Str::contains(mb_strtolower($commit->body), mb_strtolower($topic->title)),
        ))->modelKeys();
        $bindings = [];
        if ($matches !== []) {
            $score .= ' - case when id = any(?::uuid[]) then 0.1 else 0 end';
            $bindings[] = '{'.implode(',', $matches).'}';
        }

        $ids = DB::query()->fromSub($distances, 'ranked')->select('id')->orderByRaw($score, $bindings)->orderBy('id')
            ->get()->map(fn (stdClass $row): string => new Fluent((array) $row)->string('id')->value());
        $byId = $topics->keyBy('id');
        $groups = $ids->map(fn (string $id): ExploreTopic => $byId->only([$id])->sole())->groupBy('kind');
        $catalog = new Collection();
        foreach ($groups as $group) {
            $catalog = $catalog->concat($group->take(config()->integer('explore.catalog_size')));
        }

        return $catalog->values();
    }

    /**
     * @param  list<float>|null  $vector
     *
     * @phpstan-assert-if-true list<float> $vector
     */
    private function usable(?array $vector): bool
    {
        return $vector !== null && array_any($vector, fn (float $component): bool => $component !== 0.0);
    }
}
