<?php

declare(strict_types=1);

use App\Services\OpenAI;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('uses the same provider key and endpoint as the ai sdk', function (): void {
    config([
        'ai.providers.openai.key' => 'test-provider-key',
        'ai.providers.openai.url' => 'https://openai.example/v1',
    ]);
    Http::fake(['openai.example/v1/embeddings' => Http::response(embeddingsResponse([embeddingVector(3)]))]);

    expect(resolve(OpenAI::class)->embed('Hello'))->toBe(embeddingVector(3));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openai.example/v1/embeddings'
        && $request->hasHeader('Authorization', 'Bearer test-provider-key'));
});

it('asks openai nothing for nothing to embed or judge', function (): void {
    Http::fake();

    $openAI = resolve(OpenAI::class);

    expect($openAI->embedMany([]))->toBe([])
        ->and($openAI->decideRelevance('a query', []))->toBe([]);

    Http::assertNothingSent();
});
