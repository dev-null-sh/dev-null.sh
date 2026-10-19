<?php

declare(strict_types=1);

use App\Enums\SearchIndex;
use App\Services\Algolia;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('indexes with a write key and searches with a search key', function (array $config, bool $indexes, bool $searches): void {
    config(['services.algolia' => [...config()->array('services.algolia'), ...$config]]);

    $algolia = resolve(Algolia::class);

    expect($algolia->indexes())->toBe($indexes)
        ->and($algolia->searches())->toBe($searches);
})->with([
    'nothing' => [['app_id' => null, 'write_key' => null, 'search_key' => null], false, false],
    'keys without an application' => [['app_id' => null, 'write_key' => 'w', 'search_key' => 's'], false, false],
    'a write key' => [['app_id' => 'APP', 'write_key' => 'w', 'search_key' => null], true, false],
    'a search key' => [['app_id' => 'APP', 'write_key' => null, 'search_key' => 's'], false, true],
    'both keys' => [['app_id' => 'APP', 'write_key' => 'w', 'search_key' => 's'], true, true],
]);

it('prefixes index names with the environment', function (): void {
    configureAlgolia();

    expect(resolve(Algolia::class)->name(SearchIndex::Users))->toBe('testing_users');
});

it('searches the read hosts with the search key and returns the ids of the hits, best first', function (): void {
    configureAlgolia();
    Http::fake(['testapp-dsn.algolia.net/1/indexes/testing_users/query' => Http::response([
        'hits' => [['objectID' => 'b'], ['objectID' => 'a']],
    ])]);

    expect(resolve(Algolia::class)->search(SearchIndex::Users, 'nuno', 24))->toBe(['b', 'a']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('X-Algolia-Application-Id', 'TESTAPP')
        && $request->hasHeader('X-Algolia-API-Key', 'search-key')
        && $request['query'] === 'nuno'
        && $request['hitsPerPage'] === 24
        && $request['attributesToRetrieve'] === []);
});

it('throws when a search fails', function (): void {
    configureAlgolia();
    Http::fake(['testapp-dsn.algolia.net/*' => Http::response([], 500)]);

    resolve(Algolia::class)->search(SearchIndex::Users, 'nuno', 24);
})->throws(RequestException::class);

it('replaces records and removes ids in one request with the write key', function (): void {
    configureAlgolia();
    Http::fake(['testapp.algolia.net/1/indexes/testing_users/batch' => Http::response(['taskID' => 1])]);

    resolve(Algolia::class)->batch(SearchIndex::Users, [['objectID' => 'a', 'username' => 'nuno']], ['b']);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('X-Algolia-API-Key', 'write-key')
        && $request['requests'] === [
            ['action' => 'updateObject', 'body' => ['objectID' => 'a', 'username' => 'nuno']],
            ['action' => 'deleteObject', 'body' => ['objectID' => 'b']],
        ]);
});

it('configures an index with its settings', function (): void {
    configureAlgolia();
    Http::fake(['testapp.algolia.net/1/indexes/testing_users/settings' => Http::response(['taskID' => 1])]);

    resolve(Algolia::class)->configure(SearchIndex::Users);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->hasHeader('X-Algolia-API-Key', 'write-key')
        && $request['searchableAttributes'] === ['username', 'name']);
});
