<?php

declare(strict_types=1);

use App\Actions\UpdateCommitEmbedding;
use App\Models\Commit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.openai.key' => 'sk-test']);
});

it('embeds the body of a commit', function (): void {
    Http::fake(['api.openai.com/v1/embeddings' => Http::response(embeddingsResponse([embeddingVector(3)]))]);

    $commit = Commit::factory()->create(['body' => 'Shipped a new feature']);

    resolve(UpdateCommitEmbedding::class)->handle($commit);

    expect(Commit::query()->whereKey($commit->id)->value('embedding'))->toBe(embeddingVector(3));

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['model'] === 'text-embedding-3-small'
        && $request['input'] === ['Shipped a new feature']);
});

it('throws, leaving the commit unembedded, when openai returns no embedding', function (): void {
    Http::fake(['api.openai.com/v1/embeddings' => Http::response(['data' => []])]);

    $commit = Commit::factory()->create();

    expect(fn () => resolve(UpdateCommitEmbedding::class)->handle($commit))
        ->toThrow(UnexpectedValueException::class);

    expect(Commit::query()->whereKey($commit->id)->value('embedding'))->toBeNull();
});

it('discards an embedding if the text changes during the request', function (): void {
    $commit = Commit::factory()->create(['body' => 'Original body']);
    Http::fake(['api.openai.com/v1/embeddings' => function () use ($commit) {
        Commit::query()->whereKey($commit->id)->update(['body' => 'A newer correction']);

        return Http::response(embeddingsResponse([embeddingVector(3)]));
    }]);

    resolve(UpdateCommitEmbedding::class)->handle($commit);

    expect($commit->fresh()->embedding)->toBeNull()
        ->and($commit->fresh()->body)->toBe('A newer correction');
});
