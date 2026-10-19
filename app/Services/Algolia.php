<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SearchIndex;
use Illuminate\Support\Facades\Http;

final readonly class Algolia
{
    /**
     * Whether the indexes can be written to, which is all keeping them in sync needs.
     */
    public function indexes(): bool
    {
        return filled(config('services.algolia.app_id')) && filled(config('services.algolia.write_key'));
    }

    /**
     * Whether the indexes can be searched, with a search-only key, separate from the write key so the indexes can be
     * filled before anyone searches them.
     */
    public function searches(): bool
    {
        return filled(config('services.algolia.app_id')) && filled(config('services.algolia.search_key'));
    }

    /**
     * The ids of the best matches, best first, from the read-only "-dsn" host. Only ids come back: the records are
     * loaded from the database, which also decides which of them the viewer may see. Pages start at zero, and a page
     * shorter than the limit is the last one.
     *
     * @return list<string>
     */
    public function search(SearchIndex $index, string $query, int $limit, int $page = 0): array
    {
        $appId = config()->string('services.algolia.app_id');

        /** @var list<array{objectID: string}> $hits */
        $hits = Http::withHeaders(['X-Algolia-Application-Id' => $appId, 'X-Algolia-API-Key' => config()->string('services.algolia.search_key')])
            ->connectTimeout(1)
            ->timeout(1)
            ->post("https://{$appId}-dsn.algolia.net/1/indexes/{$this->name($index)}/query", [
                'query' => $query,
                'hitsPerPage' => $limit,
                'page' => $page,
                'attributesToRetrieve' => [],
                'attributesToHighlight' => [],
                'attributesToSnippet' => [],
            ])
            ->throw()
            ->json('hits', []);

        return array_column($hits, 'objectID');
    }

    /**
     * Replaces the given records and removes the given ids in one request. Algolia counts an operation per record either
     * way, but one request is one task in its queue, rather than one per record.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $deletedIds
     */
    public function batch(SearchIndex $index, array $records, array $deletedIds): void
    {
        $appId = config()->string('services.algolia.app_id');

        Http::withHeaders(['X-Algolia-Application-Id' => $appId, 'X-Algolia-API-Key' => config()->string('services.algolia.write_key')])
            ->connectTimeout(2)
            ->timeout(5)
            ->post("https://{$appId}.algolia.net/1/indexes/{$this->name($index)}/batch", ['requests' => [
                ...array_map(fn (array $record): array => ['action' => 'updateObject', 'body' => $record], $records),
                ...array_map(fn (string $id): array => ['action' => 'deleteObject', 'body' => ['objectID' => $id]], $deletedIds),
            ]])
            ->throw();
    }

    public function configure(SearchIndex $index): void
    {
        $appId = config()->string('services.algolia.app_id');

        Http::withHeaders(['X-Algolia-Application-Id' => $appId, 'X-Algolia-API-Key' => config()->string('services.algolia.write_key')])
            ->connectTimeout(2)
            ->timeout(5)
            ->put("https://{$appId}.algolia.net/1/indexes/{$this->name($index)}/settings", $index->settings())
            ->throw();
    }

    /**
     * Prefixed with the environment, so local, staging and production can share one application.
     */
    public function name(SearchIndex $index): string
    {
        return config()->string('app.env').'_'.$index->value;
    }
}
