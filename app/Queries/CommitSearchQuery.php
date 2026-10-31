<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\EmbeddingProvider;
use App\Contracts\Services\RelevanceProvider;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class CommitSearchQuery
{
    /**
     * An hour of remembering the vector for a term: the search dialog debounces, so the same words arrive several times
     * as someone types, and the same words are often typed again by someone else.
     */
    private const int CACHE_SECONDS = 3600;

    /**
     * Shorter than any word worth embedding. Guarded here as well as in the request, so a stray call never spends an
     * embedding on "a" or "on".
     */
    private const int MINIMUM_TERM_LENGTH = 3;

    public function __construct(private EmbeddingProvider $embeddings, private RelevanceProvider $relevance)
    {
        //
    }

    /**
     * The commits nearest in meaning to the term, closest first, loaded as the feeds load them so the same card renders
     * them. Commits the viewer may not see, and commits whose author they muted or blocked, are left out, as are
     * commits that have not been embedded yet.
     * A commit uses its stored embedding and body to find top-level commits outside its own thread.
     *
     * @return Collection<int, Commit>
     */
    public function get(string|Commit $term, ?User $viewer, int $limit = 20): Collection
    {
        $source = $term instanceof Commit
            ? Commit::query()->whereKey($term->id)->first(['id', 'body', 'embedding', 'parent_id', 'root_id'])
            : null;

        if ($term instanceof Commit && $source?->embedding === null) {
            return new Collection();
        }

        $term = mb_trim($term instanceof Commit ? $source->body : $term);

        if ($source === null && mb_strlen($term) < self::MINIMUM_TERM_LENGTH) {
            return new Collection();
        }

        /** @var list<float> $vector */
        $vector = $source->embedding ?? Cache::remember('search-embedding:'.hash('sha256', $term), self::CACHE_SECONDS, fn (): array => $this->embeddings->embed($term));

        $candidates = DB::transaction(function () use ($source, $vector, $viewer, $limit): Collection {
            if ($source instanceof Commit) {
                DB::statement('set local hnsw.iterative_scan = strict_order');
            }

            return Commit::query()
                ->whereIn('commits.id', $this->nearest($vector, $viewer, $limit * 2, $source))
                ->visibleTo($viewer)
                ->withoutMutedAuthorsFor($viewer)
                ->withVisibleReplyCount($viewer)
                ->withContinuationId($viewer)
                ->withCardDataFor($viewer)
                ->orderByVectorDistance('embedding', $vector)
                ->limit($limit)
                ->get()
                ->toBase();
        });

        return $this->onlyRelevant($term, $candidates, related: $source instanceof Commit);
    }

    /**
     * The nearest commits are looked up on the vector index before the blocks and mutes are checked. With those checks
     * in the same query, the database expects so few commits to match that it compares the term against every
     * embedding instead, reading all of them from disk. Twice as many as asked for leaves room for the ones the checks
     * then leave out.
     *
     * @param  list<float>  $vector
     */
    private function nearest(array $vector, ?User $viewer, int $count, ?Commit $source): CommitBuilder
    {
        return Commit::query()
            ->select('id')
            ->when($source, fn (CommitBuilder $query, Commit $source): CommitBuilder => $query
                ->topLevel()
                ->whereKeyNot($source->threadRootId())
                ->visibleTo($viewer)
                ->withoutMutedAuthorsFor($viewer))
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->approved()
                ->when($viewer, fn (CommitBuilder $query, User $viewer): CommitBuilder => $query->orWhere('user_id', $viewer->id)))
            ->whereNotNull('embedding')
            ->orderByVectorDistance('embedding', $vector)
            ->limit($count);
    }

    /**
     * The vector search is generous: the nearest by meaning can include posts that merely share a word with the term.
     * The decision model reads the term against each candidate and keeps meaningful connections to it, so the
     * caller may get fewer than it asked for.
     *
     * @param  Collection<int, Commit>  $candidates
     * @return Collection<int, Commit>
     */
    private function onlyRelevant(string $term, Collection $candidates, bool $related): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $relevant = $this->relevance->decideRelevance($term, array_values($candidates->map(fn (Commit $commit): string => $commit->body)->all()), related: $related);

        return $candidates
            ->filter(fn (Commit $commit, int $index): bool => $relevant[$index] ?? true)
            ->values();
    }
}
