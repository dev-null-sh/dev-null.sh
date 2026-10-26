<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;
use UnexpectedValueException;

use function Laravel\Ai\agent;

final readonly class OpenAI
{
    /**
     * The width of text-embedding-3-small's vectors, and so of the commits.embedding column.
     */
    public const int EMBEDDING_DIMENSIONS = 1536;

    public const string EMBEDDING_MODEL = 'text-embedding-3-small';

    private const string MODERATION_MODEL = 'omni-moderation-latest';

    private const string REVIEW_MODEL = 'gpt-6.1-sol';

    /**
     * The efficient model, for the relevance verdict on every candidate of every search.
     */
    private const string DECISION_MODEL = 'gpt-6-luna';

    /**
     * Far stricter than OpenAI's own cut-off: any category scoring this much is flagged.
     */
    private const float STRICT_THRESHOLD = 0.2;

    /**
     * @var array<string, float>
     */
    private const array THRESHOLDS = [
        'sexual/minors' => 0.01,
    ];

    /**
     * Keep plausible connections, not just confident matches; discard candidates below 30% relevance probability.
     */
    private const float RELEVANCE_THRESHOLD = 0.3;

    private const string HARMFUL_INSTRUCTIONS = <<<'TEXT'
        You review posts on /dev/null, a social network for developers, before they are published.
        Answer whether the post is harmful: a threat, including claims of having a bomb, weapon or explosive, or of
        planning an attack, even as a joke; violence, incitement or glorification of it; hate speech or harassment of a
        person or group; sexual content; encouragement of self-harm; or illegal activity. Developer jargon such as
        "kill the process", "nuke the database" or "this release is the bomb" is not harmful. Disliking a technology
        is not harmful either. When unsure, answer true.
        TEXT;

    private const string RELEVANCE_INSTRUCTIONS = <<<'TEXT'
        You review search results for devnull, a social network for developers.
        You are given a query and a numbered list of posts. For each post, answer whether it has a meaningful connection
        to the query. Keep related discussions, personal experiences, opinions, jokes and livestream updates, even if
        they only cover part of the topic or use different words. A post does not need to directly answer a question.
        For example, experiences with coding agents can match AI coding, and pull requests can match contributing on
        GitHub. A shared word alone is not enough: leave out posts with no meaningful connection to the topic.
        Answer with one boolean per post, in the same order.
        TEXT;

    public function request(): PendingRequest
    {
        return Http::baseUrl(config()->string('ai.providers.openai.url'))
            ->withToken(config()->string('ai.providers.openai.key'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(15);
    }

    /**
     * The vector for a single text, for the one-off embeds a commit gets when it is created or searched for.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0];
    }

    /**
     * The vectors for several texts in one request, in the order they were given, for backfills. The API caps a
     * request at 2048 inputs, so callers send them in batches well under that.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        /** @var list<array{index: int, embedding: list<float>}> $data */
        $data = $this->request()
            ->timeout(30)
            ->post('embeddings', ['model' => self::EMBEDDING_MODEL, 'input' => $texts])
            ->throw()
            ->json('data');

        throw_if(count($data) !== count($texts), UnexpectedValueException::class, 'OpenAI did not return an embedding for every input.');

        usort($data, fn (array $result, array $other): int => $result['index'] <=> $other['index']);

        return array_map(fn (array $result): array => array_map(floatval(...), $result['embedding']), $data);
    }

    /**
     * Whether each body is meaningfully related to the query, in the order they were given. The vector search returns
     * the nearest by meaning, which can include posts that merely share a word; the decision model filters those out
     * while keeping plausible connections. Anything but a clear answer, such as a refusal, a post the model
     * skipped, or a probability that can't be read, counts as relevant, so a flaky model never hides a post.
     *
     * @param  list<string>  $bodies
     * @return list<bool>
     */
    public function decideRelevance(string $query, array $bodies): array
    {
        if ($bodies === []) {
            return [];
        }

        $questions = [];

        foreach (array_keys($bodies) as $index) {
            $questions[] = [
                'name' => 'post_'.($index + 1),
                'type' => 'predicate',
                'instructions' => 'Does post '.($index + 1).' have a meaningful connection to the query, even if it only covers part of the topic or does not directly answer it?',
            ];
        }

        $response = $this->request()
            ->timeout(60)
            ->post('decisions', [
                'model' => self::DECISION_MODEL,
                'input' => self::RELEVANCE_INSTRUCTIONS."\n\n".$this->relevancePrompt($query, $bodies),
                'questions' => $questions,
            ])
            ->throw()
            ->json();

        return $this->relevanceVerdicts($response, count($bodies));
    }

    /**
     * The categories the content is flagged for, if any, each with its highest score: those OpenAI flags, and those
     * scoring above our own, stricter thresholds. The text, if any, goes along with the first image, and every other
     * image is checked on its own, as a single request is only documented to take one image.
     *
     * @param  list<string>  $imageDataUrls
     * @return array<string, float>
     */
    public function moderate(string $text, array $imageDataUrls = []): array
    {
        $inputs = $text === '' ? [] : [[['type' => 'text', 'text' => $text]]];

        foreach ($imageDataUrls as $index => $imageDataUrl) {
            $image = ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]];

            if ($index === 0 && $inputs !== []) {
                $inputs[0][] = $image;
            } else {
                $inputs[] = [$image];
            }
        }

        $categories = [];

        foreach ($inputs as $input) {
            /** @var list<array{categories: array<string, bool>, category_scores: array<string, float>}> $results */
            $results = $this->request()
                ->post('moderations', ['model' => self::MODERATION_MODEL, 'input' => $input])
                ->throw()
                ->json('results');

            foreach ($results as $result) {
                foreach ($result['categories'] as $category => $isFlagged) {
                    $score = $result['category_scores'][$category] ?? 0.0;

                    if ($isFlagged || $score >= (self::THRESHOLDS[$category] ?? self::STRICT_THRESHOLD)) {
                        $categories[$category] = max($categories[$category] ?? 0.0, $score);
                    }
                }
            }
        }

        return $categories;
    }

    /**
     * Whether the text is harmful. The moderation model misses threats that are implied rather than spelled out, such
     * as claiming to have a bomb, so a reasoning model is asked too. Anything but a clear no counts as a yes.
     */
    public function isHarmful(string $text): bool
    {
        $answer = agent(
            instructions: self::HARMFUL_INSTRUCTIONS,
            schema: fn (JsonSchema $schema): array => ['harmful' => $schema->boolean()->required()],
        )->prompt(
            $text,
            provider: Lab::OpenAI,
            model: self::REVIEW_MODEL,
            timeout: 60,
        );

        return ($answer['harmful'] ?? null) !== false;
    }

    /**
     * @param  list<string>  $bodies
     */
    private function relevancePrompt(string $query, array $bodies): string
    {
        $posts = [];

        foreach ($bodies as $index => $body) {
            $posts[] = ($index + 1).'. '.$body;
        }

        return "Query: {$query}\n\nPosts:\n".implode("\n", $posts);
    }

    /**
     * One verdict per post, in the order they were given: kept at or above the relevance threshold. A post
     * the answer skips, and one whose probability can't be read, is kept.
     *
     * @return list<bool>
     */
    private function relevanceVerdicts(mixed $response, int $count): array
    {
        $answers = is_array($response) ? ($response['answers'] ?? null) : null;

        $probabilities = [];

        if (is_array($answers)) {
            foreach ($answers as $answer) {
                $predicate = $this->predicateAnswer($answer);

                if ($predicate !== null) {
                    $probabilities[$predicate['name']] = $predicate['probability'];
                }
            }
        }

        $verdicts = [];

        for ($index = 0; $index < $count; $index++) {
            $probability = $probabilities['post_'.($index + 1)] ?? null;

            $verdicts[] = $probability === null || $probability >= self::RELEVANCE_THRESHOLD;
        }

        return $verdicts;
    }

    /**
     * The name and probability of a predicate answer, or null for anything else: a refusal, an answer to another
     * question, or a probability that can't be read as a number.
     *
     * @return array{name: string, probability: float}|null
     */
    private function predicateAnswer(mixed $answer): ?array
    {
        if (! is_array($answer) || ($answer['type'] ?? null) !== 'predicate') {
            return null;
        }

        $name = $answer['name'] ?? null;
        $probability = $answer['probability'] ?? null;

        if (! is_string($name) || ! is_numeric($probability)) {
            return null;
        }

        return ['name' => $name, 'probability' => (float) $probability];
    }
}
