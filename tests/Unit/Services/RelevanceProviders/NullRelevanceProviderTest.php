<?php

declare(strict_types=1);

use App\Contracts\Services\RelevanceProvider;
use App\Services\RelevanceProviders\NullRelevanceProvider;

it('is the relevance provider in testing', function (): void {
    expect(resolve(RelevanceProvider::class))->toBeInstanceOf(NullRelevanceProvider::class);
});

it('keeps every result', function (): void {
    $provider = new NullRelevanceProvider();

    expect($provider->decideRelevance('a query', ['About indexing', 'About something else']))->toBe([true, true])
        ->and($provider->decideRelevance('a query', []))->toBe([]);
});
