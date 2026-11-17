<?php

declare(strict_types=1);

use App\Contracts\Services\EmbeddingProvider;
use App\Jobs\EmbedCommit;
use App\Models\Commit;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

it('skips commits that were deleted in the meantime', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldNotReceive('embed');

    $commit = Commit::factory()->create();
    $commit->delete();

    dispatch_sync(new EmbedCommit($commit));
});

it('throws when openai is unavailable, so the job is retried', function (): void {
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embed')->andThrow(new RequestException(new Response(new Psr7Response(503))));

    $commit = Commit::factory()->create();

    expect(fn () => dispatch_sync(new EmbedCommit($commit)))->toThrow(RequestException::class)
        ->and(Commit::query()->whereKey($commit->id)->value('embedding'))->toBeNull();
});
