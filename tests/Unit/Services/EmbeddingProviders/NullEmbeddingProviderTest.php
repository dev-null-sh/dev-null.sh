<?php

declare(strict_types=1);

use App\Contracts\Services\EmbeddingProvider;
use App\Services\EmbeddingProviders\NullEmbeddingProvider;

it('is the embedding provider in testing', function (): void {
    expect(resolve(EmbeddingProvider::class))->toBeInstanceOf(NullEmbeddingProvider::class);
});

it('gives every text the same unit vector', function (): void {
    $provider = new NullEmbeddingProvider();

    expect($provider->embed('Hello'))->toBe(embeddingVector(0))
        ->and($provider->embedMany(['First commit', 'Second commit']))->toBe([embeddingVector(0), embeddingVector(0)])
        ->and($provider->embedMany([]))->toBe([]);
});
