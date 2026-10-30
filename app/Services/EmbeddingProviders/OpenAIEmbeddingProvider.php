<?php

declare(strict_types=1);

namespace App\Services\EmbeddingProviders;

use App\Contracts\Services\EmbeddingProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final readonly class OpenAIEmbeddingProvider implements EmbeddingProvider
{
    /**
     * Its vectors are EmbeddingProvider::DIMENSIONS wide: another model means embedding everything again.
     */
    private const string MODEL = 'text-embedding-3-small';

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
            ->post('embeddings', ['model' => self::MODEL, 'input' => $texts])
            ->throw()
            ->json('data');

        throw_if(count($data) !== count($texts), UnexpectedValueException::class, 'OpenAI did not return an embedding for every input.');

        usort($data, fn (array $result, array $other): int => $result['index'] <=> $other['index']);

        return array_map(fn (array $result): array => array_map(floatval(...), $result['embedding']), $data);
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
