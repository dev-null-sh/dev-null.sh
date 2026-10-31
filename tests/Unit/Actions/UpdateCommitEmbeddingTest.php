<?php

declare(strict_types=1);

use App\Actions\UpdateCommitEmbedding;
use App\Contracts\Services\EmbeddingProvider;
use App\Models\Commit;

it('embeds the body of a commit', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embed')->once()->with('Shipped a new feature')->andReturn(embeddingVector(3));

    $commit = Commit::factory()->create(['body' => 'Shipped a new feature']);

    resolve(UpdateCommitEmbedding::class)->handle($commit);

    expect(Commit::query()->whereKey($commit->id)->value('embedding'))->toBe(embeddingVector(3));
});

it('throws, leaving the commit unembedded, when no embedding comes back', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embed')->andThrow(new UnexpectedValueException('OpenAI did not return an embedding for every input.'));

    $commit = Commit::factory()->create();

    expect(fn () => resolve(UpdateCommitEmbedding::class)->handle($commit))
        ->toThrow(UnexpectedValueException::class);

    expect(Commit::query()->whereKey($commit->id)->value('embedding'))->toBeNull();
});
