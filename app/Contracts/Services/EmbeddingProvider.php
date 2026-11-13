<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Turns text into vectors, so commits and knowledge base sections can be searched by meaning.
 */
interface EmbeddingProvider
{
    /**
     * The width of every vector, and so of the embedding columns: changing it means embedding everything again.
     */
    public const int DIMENSIONS = 1536;

    /**
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * The vectors for several texts in one request, in the order they were given, for backfills. Callers send them in
     * batches well under the 2048 inputs a request takes.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array;
}
