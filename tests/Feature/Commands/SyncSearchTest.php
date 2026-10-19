<?php

declare(strict_types=1);

use App\Actions\CreateSearchSyncs;
use App\Actions\UpdateSearchIndex;
use App\Enums\SearchIndex;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('sends every waiting change, a batch per request', function (): void {
    configureAlgolia();
    Http::fake(['testapp.algolia.net/1/indexes/testing_users/batch' => Http::response(['taskID' => 1])]);

    resolve(CreateSearchSyncs::class)->handle(
        SearchIndex::Users,
        array_map(fn (): string => Str::uuid7()->toString(), range(1, UpdateSearchIndex::BATCH_SIZE + 1)),
    );

    $this->artisan('search:sync')
        ->expectsOutputToContain('Sent '.(UpdateSearchIndex::BATCH_SIZE + 1).' changes.')
        ->assertSuccessful();

    expect(pendingSearchSyncs())->toBe([]);

    Http::assertSentCount(2);
});

it('does nothing while Algolia is not set up', function (): void {
    Http::fake();

    $this->artisan('search:sync')
        ->expectsOutputToContain('Algolia is not configured.')
        ->assertSuccessful();

    Http::assertNothingSent();
});
