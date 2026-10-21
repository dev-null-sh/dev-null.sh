<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\ExploreClassifier;
use App\Models\Commit;
use App\Models\ExploreTopic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ExploreBatch
{
    /**
     * Measure cosine distance between two embedding vectors. Smaller distances
     * indicate more similar directions: 0 means the same direction, 1 means
     * perpendicular directions, and 2 means opposite directions.
     *
     * $dotProduct is the sum of corresponding components multiplied together.
     * $leftLengthSquared and $rightLengthSquared are the sums of squared
     * components in each vector. $lengthProduct is the product of their lengths,
     * which normalizes the dot product into cosine similarity. Subtracting that
     * similarity from 1 gives distance, so vector direction matters rather than
     * vector length.
     *
     * Missing vectors, different dimensions, or zero-length vectors return 2
     * so unusable comparisons sort after more similar valid comparisons.
     *
     * @param  list<float>|null  $leftVector
     * @param  list<float>|null  $rightVector
     */
    public function distance(?array $leftVector, ?array $rightVector): float
    {
        if ($leftVector === null || $rightVector === null || count($leftVector) !== count($rightVector)) {
            return 2.0;
        }

        $dotProduct = 0.0;
        $leftLengthSquared = 0.0;
        $rightLengthSquared = 0.0;
        foreach ($leftVector as $dimension => $leftValue) {
            $rightValue = $rightVector[$dimension];

            $dotProduct += $leftValue * $rightValue;
            $leftLengthSquared += $leftValue ** 2;
            $rightLengthSquared += $rightValue ** 2;
        }

        if ($leftLengthSquared > 0 && $rightLengthSquared > 0) {
            $lengthProduct = sqrt($leftLengthSquared * $rightLengthSquared);

            return 1 - $dotProduct / $lengthProduct;
        }

        return 2.0;
    }

    /**
     * Prepare one classification request from commits ordered oldest first.
     * Keep the first commit as the seed and, when it has an embedding and
     * semantic ordering is enabled, select its closest remaining neighbours.
     * Otherwise, preserve the supplied order. The request size is configurable.
     *
     * Build a catalogue of recent, unsuppressed subjects and stories using
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

        $ordered = $semantic && $seed->embedding !== null
            ? $waiting->reject(fn (Commit $commit): bool => $commit->is($seed))
                ->sortBy(fn (Commit $commit): float => $this->distance($seed->embedding, $commit->embedding))->prepend($seed)
            : $waiting;
        $commits = $ordered->take(config()->integer('explore.request_size'))->values();
        $vectors = DB::table('explore_commit_topic as membership')->join('commits', 'commits.id', '=', 'membership.commit_id')
            ->whereNull('commits.deleted_at')->where('commits.status', 'approved')
            ->where('commits.created_at', '>=', now()->subDays(7))->whereNotNull('commits.embedding')
            ->select('membership.topic_id')->selectRaw('avg(commits.embedding)::text as vector')
            ->groupBy('membership.topic_id')->pluck('vector', 'topic_id');
        $topics = ExploreTopic::query()->whereNull('suppressed_at')->where('updated_at', '>=', now()->subDays(7))->orderBy('id')->get()
            ->sortBy(function (ExploreTopic $topic) use ($vectors, $commits): float {
                $raw = $vectors->get($topic->id);
                $decoded = is_string($raw) ? json_decode($raw, true, flags: JSON_THROW_ON_ERROR) : null;
                $vector = is_array($decoded) ? array_values(array_map(fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0, $decoded)) : null;

                return $commits->min(fn (Commit $commit): float => $this->distance($commit->embedding, $vector))
                    - ($commits->contains(fn (Commit $commit): bool => Str::contains(mb_strtolower($commit->body), mb_strtolower($topic->title))) ? 0.1 : 0);
            })
            ->groupBy('kind')->flatMap(fn (Collection $group): Collection => $group->take(config()->integer('explore.catalog_size')))->values();
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
