<?php

declare(strict_types=1);

namespace App\Services\EmbeddingProviders;

use App\Contracts\Services\EmbeddingProvider;

/**
 * The same unit vector for every text, so developing and testing never reach a third party: searches still run, at
 * the same distance from everything, without ranking anything by meaning. A zero vector would have no cosine distance.
 */
final readonly class NullEmbeddingProvider implements EmbeddingProvider
{
    public function embed(string $text): array
    {
        return [1.0, ...array_fill(0, self::DIMENSIONS - 1, 0.0)];
    }

    public function embedMany(array $texts): array
    {
        return array_map($this->embed(...), $texts);
    }
}
