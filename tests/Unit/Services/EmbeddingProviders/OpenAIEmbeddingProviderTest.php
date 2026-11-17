<?php

declare(strict_types=1);

use App\Services\EmbeddingProviders\OpenAIEmbeddingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('uses the same provider key and endpoint as the ai sdk', function (): void {
    config([
        'ai.providers.openai.key' => 'test-provider-key',
        'ai.providers.openai.url' => 'https://openai.example/v1',
    ]);
    Http::fake(['openai.example/v1/embeddings' => Http::response(embeddingsResponse([embeddingVector(3)]))]);

    expect(new OpenAIEmbeddingProvider()->embed('Hello'))->toBe(embeddingVector(3));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openai.example/v1/embeddings'
        && $request->hasHeader('Authorization', 'Bearer test-provider-key')
        && $request['model'] === 'text-embedding-3-small'
        && $request['input'] === ['Hello']);
});

it('embeds several texts in one request, in the order they were given', function (): void {
    Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => [
        ['index' => 1, 'embedding' => embeddingVector(2)],
        ['index' => 0, 'embedding' => embeddingVector(1)],
    ]])]);

    expect(new OpenAIEmbeddingProvider()->embedMany(['First commit', 'Second commit']))->toBe([embeddingVector(1), embeddingVector(2)]);

    Http::assertSent(fn (Request $request): bool => $request['input'] === ['First commit', 'Second commit']);
    Http::assertSentCount(1);
});

it('asks openai nothing for nothing to embed', function (): void {
    Http::fake();

    expect(new OpenAIEmbeddingProvider()->embedMany([]))->toBe([]);

    Http::assertNothingSent();
});

it('fails when openai does not return an embedding for every text', function (): void {
    Http::fake(['api.openai.com/v1/embeddings' => Http::response(embeddingsResponse([embeddingVector(1)]))]);

    expect(fn (): array => new OpenAIEmbeddingProvider()->embedMany(['First commit', 'Second commit']))
        ->toThrow(UnexpectedValueException::class, 'OpenAI did not return an embedding for every input.');
});

it('fails when openai refuses', function (): void {
    Http::fake(['api.openai.com/v1/embeddings' => Http::response([], 500)]);

    expect(fn (): array => new OpenAIEmbeddingProvider()->embed('Hello'))->toThrow(RequestException::class);
});
