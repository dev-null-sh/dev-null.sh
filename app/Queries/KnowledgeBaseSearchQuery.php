<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\EmbeddingProvider;
use App\Models\KnowledgeBaseSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class KnowledgeBaseSearchQuery
{
    private const int CACHE_SECONDS = 3600;

    public function __construct(private EmbeddingProvider $embeddings)
    {
        //
    }

    /**
     * @return Collection<int, KnowledgeBaseSection>
     */
    public function get(string $term, int $limit = 5): Collection
    {
        $term = mb_trim($term);

        if ($term === '' || $limit <= 0) {
            return new Collection();
        }

        /** @var list<float> $embedding */
        $embedding = Cache::remember('search-embedding:'.hash('sha256', $term), self::CACHE_SECONDS, fn (): array => $this->embeddings->embed($term));

        return KnowledgeBaseSection::query()
            ->select(['id', 'source', 'position', 'headings', 'content'])
            ->selectVectorDistance('embedding', $embedding, as: 'distance')
            ->withCasts(['distance' => 'float'])
            ->orderByVectorDistance('embedding', $embedding)
            ->orderBy('source')
            ->orderBy('position')
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
