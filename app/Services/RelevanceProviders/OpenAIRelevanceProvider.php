<?php

declare(strict_types=1);

namespace App\Services\RelevanceProviders;

use App\Contracts\Services\RelevanceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class OpenAIRelevanceProvider implements RelevanceProvider
{
    /**
     * The efficient model, for the relevance verdict on every candidate of every search.
     */
    private const string DECISION_MODEL = 'gpt-6-luna';

    /**
     * Keep plausible connections, not just confident matches; discard candidates below 30% relevance probability.
     */
    private const float RELEVANCE_THRESHOLD = 0.3;

    private const string RELEVANCE_INSTRUCTIONS = <<<'TEXT'
        You review search results for devnull, a social network for developers.
        You are given a query and a numbered list of posts. For each post, answer whether it has a meaningful connection
        to the query. Keep related discussions, personal experiences, opinions, jokes and livestream updates, even if
        they only cover part of the topic or use different words. A post does not need to directly answer a question.
        For example, experiences with coding agents can match AI coding, and pull requests can match contributing on
        GitHub. A shared word alone is not enough: leave out posts with no meaningful connection to the topic.
        Answer with one boolean per post, in the same order.
        TEXT;

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

    private function request(): PendingRequest
    {
        $url = config('ai.providers.openai.url');
        assert(is_string($url));

        $key = config('ai.providers.openai.key');
        assert(is_string($key));

        return Http::baseUrl($url)
            ->withToken($key)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(15);
    }
}
