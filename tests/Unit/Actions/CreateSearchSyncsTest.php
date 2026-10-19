<?php

declare(strict_types=1);

use App\Actions\CreateSearchSyncs;
use App\Enums\SearchIndex;

it('notes each changed object once', function (): void {
    configureAlgolia();

    resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, ['a', 'b', 'a']);

    expect(pendingSearchSyncs())->toBe(['a', 'b']);
});

it('notes nothing for no objects', function (): void {
    configureAlgolia();

    resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, []);

    expect(pendingSearchSyncs())->toBe([]);
});

it('notes nothing while Algolia is not set up', function (): void {
    resolve(CreateSearchSyncs::class)->handle(SearchIndex::Users, ['a']);

    expect(pendingSearchSyncs())->toBe([]);
});
