<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\ExploreClassifier;
use App\Models\Commit;
use App\Models\ExploreTopic;
use App\Queries\ExploreBatchQuery;
use Illuminate\Support\Collection;
use RuntimeException;

final readonly class ExploreBatch
{
    public function __construct(private ExploreBatchQuery $query)
    {
        //
    }

    /**
     * Prepare one classification request from commits ordered oldest first.
     * Keep the first commit as the seed and, when it has an embedding and
     * semantic ordering is enabled, select its closest remaining neighbours.
     * Otherwise, preserve the supplied order. The request size is configurable.
     *
     * Delegate neighbour and topic ranking to ExploreBatchQuery. Build a
     * catalogue of recent, unsuppressed subjects and stories using
     * average member embeddings from the last seven days. Prefer topics close
     * to any selected commit, with a small boost for matching topic titles.
     * Apply the configured catalogue limit separately to subjects and stories.
     *
     * Encode full commit bodies and timestamps with short c0/t0 references.
     * Keep database IDs, author identities, and embeddings out of the prompt.
     * To fit the byte budget, including instructions and 4 KiB of headroom,
     * remove catalogue entries first, then commits. Never truncate a body.
     * An empty input or a single commit that cannot fit raises an exception.
     *
     * Return the prompt and local commit/topic mappings so the caller can
     * resolve response references, persist classifications, and pack the rest.
     *
     * @param  Collection<int, Commit>  $waiting
     * @return array{commits: Collection<int, Commit>, catalog: array<string, ExploreTopic>, prompt: string}
     */
    public function pack(Collection $waiting, bool $semantic = true): array
    {
        $seed = $waiting->first();
        throw_unless($seed instanceof Commit, RuntimeException::class, 'Explore batch is empty.');

        $commits = $this->query->commits($waiting, $semantic);
        $topics = $this->query->topics($commits);
        $catalog = [];
        foreach ($topics as $index => $topic) {
            $catalog['t'.$index] = $topic;
        }

        do {
            $prompt = json_encode([
                'columns' => ['ref', 'posted_at', 'text'],
                'commits' => $commits->map(fn (Commit $commit, int $index): array => ['c'.$index, $commit->created_at->toIso8601String(), $commit->body])->all(),
                'catalog' => collect($catalog)->map(fn (ExploreTopic $topic, string $ref): array => [
                    'ref' => $ref, 'kind' => $topic->kind, 'key' => $topic->key, 'title' => $topic->title, 'categories' => $topic->categories,
                ])->values()->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (mb_strlen($prompt, '8bit') + mb_strlen(new ExploreClassifier()->instructions(), '8bit') + 4096 <= config()->integer('explore.prompt_bytes')) {
                return ['commits' => $commits, 'catalog' => $catalog, 'prompt' => $prompt];
            }

            if ($catalog !== []) {
                array_pop($catalog);
            } elseif ($commits->count() > 1) {
                $commits = $commits->take($commits->count() - 1);
            } else {
                throw new RuntimeException('Explore input exceeds the configured prompt budget.');
            }
        } while (true);
    }
}
