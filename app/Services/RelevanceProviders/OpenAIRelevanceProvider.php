<?php

declare(strict_types=1);

namespace App\Services\RelevanceProviders;

use App\Contracts\Services\RelevanceProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    private const string RELATED_COMMIT_INSTRUCTIONS = <<<'TEXT'
        You select related commits to show beneath an original commit on devnull, a social network for developers.
        The candidates came from a semantic similarity search, but similarity does not establish relatedness.
        Classify each candidate by whether its actual subject has a meaningful connection to the original commit's
        subject. Related discussions, complementary experiences, different approaches and opposing opinions can qualify.
        Similar sentiment, tone, reactions or conversational intent do not establish a shared subject. For example,
        "that's so cool!" about a dog photo is not related to "that's cool!" about a devnull feature announcement.
        Generic reactions without an identifiable subject are not evidence of relatedness: do not invent missing
        context. A candidate must offer a concrete connection to what the original is about, not merely sound similar.
        Answer with one boolean per candidate, in the same order.
        TEXT;

    /**
     * Whether each body is meaningfully related to the query, in the order they were given. The vector search returns
     * the nearest by meaning, which can include posts that merely share a word; the decision model filters those out
     * while keeping plausible connections. Anything but a clear answer, such as a refusal, a post the model
     * skipped, or a probability that can't be read, counts as relevant, so a flaky model never hides a post.
     * Classification is optional: timeouts and HTTP failures keep every candidate.
     *
     * @param  list<string>  $bodies
     * @return list<bool>
     */
    public function decideRelevance(string $query, array $bodies, bool $related = false): array
    {
        if ($bodies === []) {
            return [];
        }

        $questions = [];

        foreach (array_keys($bodies) as $index) {
            $questions[] = [
                'name' => 'post_'.($index + 1),
                'type' => 'predicate',
                'instructions' => $related
                    ? 'Does the actual subject of post '.($index + 1)." have a concrete connection to the original commit's subject, rather than merely expressing a similar reaction, sentiment or conversational intent?"
                    : 'Does post '.($index + 1).' have a meaningful connection to the query, even if it only covers part of the topic or does not directly answer it?',
            ];
        }

        try {
            $response = $this->request()
                ->connectTimeout(1)
                ->timeout(2)
                ->post('decisions', [
                    'model' => self::DECISION_MODEL,
                    'input' => ($related ? self::RELATED_COMMIT_INSTRUCTIONS : self::RELEVANCE_INSTRUCTIONS)
                        ."\n\n".$this->relevancePrompt($query, $bodies, $related),
                    'questions' => $questions,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            if ($exception instanceof ConnectionException && str_contains($exception->getMessage(), 'cURL error 28:')) {
                Log::warning('Commit relevance classifier timed out.', ['related' => $related, 'timeout_seconds' => 2]);
            }

            return array_fill(0, count($bodies), true);
        }

        return $this->relevanceVerdicts($response, count($bodies));
    }

    /**
     * @param  list<string>  $bodies
     */
    private function relevancePrompt(string $query, array $bodies, bool $related): string
    {
        $posts = [];

        foreach ($bodies as $index => $body) {
            $posts[] = ($index + 1).'. '.$body;
        }

        return ($related ? 'Original commit' : 'Query').": {$query}\n\nPosts:\n".implode("\n", $posts);
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
