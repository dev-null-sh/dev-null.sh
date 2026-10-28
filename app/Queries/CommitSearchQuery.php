<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Commit;
use App\Models\User;
use App\Services\OpenAI;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

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

    public function __construct(private OpenAI $openAI)
    {
        //
    }

    /**
     * The commits nearest in meaning to the term, closest first, loaded as the feeds load them so the same card renders
     * them. Commits the viewer may not see, and commits whose author they muted or blocked, are left out, as are
     * commits that have not been embedded yet.
     *
     * @return Collection<int, Commit>
     */
    public function get(string $term, ?User $viewer, int $limit = 20): Collection
    {
        $term = mb_trim($term);

        if (mb_strlen($term) < self::MINIMUM_TERM_LENGTH) {
            return new Collection();
        }

        $embedding = Embeddings::for([$term])
            ->dimensions(OpenAI::EMBEDDING_DIMENSIONS)
            ->cache(self::CACHE_SECONDS)
            ->generate(provider: Lab::OpenAI, model: OpenAI::EMBEDDING_MODEL)
            ->first();

        $vector = array_values($embedding);

        $candidates = Commit::query()
            ->whereIn('commits.id', $this->nearest($vector, $viewer, $limit * 2))
            ->visibleTo($viewer)
            ->withoutMutedAuthorsFor($viewer)
            ->withVisibleReplyCount($viewer)
            ->withContinuationId($viewer)
            ->withCardDataFor($viewer)
            ->orderByVectorDistance('embedding', $vector)
            ->limit($limit)
            ->get()
            ->toBase();

        return $this->onlyRelevant($term, $candidates);
    }

    /**
     * The nearest commits are looked up on the vector index before the blocks and mutes are checked. With those checks
     * in the same query, the database expects so few commits to match that it compares the term against every
     * embedding instead, reading all of them from disk. Twice as many as asked for leaves room for the ones the checks
     * then leave out.
     *
     * @param  list<float>  $vector
     */
    private function nearest(array $vector, ?User $viewer, int $count): CommitBuilder
    {
        return Commit::query()
            ->select('id')
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
    private function onlyRelevant(string $term, Collection $candidates): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $relevant = $this->openAI->decideRelevance($term, array_values($candidates->map(fn (Commit $commit): string => $commit->body)->all()));

        return $candidates
            ->filter(fn (Commit $commit, int $index): bool => $relevant[$index] ?? true)
            ->values();
    }
}
