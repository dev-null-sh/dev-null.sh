<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SearchIndex;
use App\Services\Algolia;
use Illuminate\Support\Facades\DB;

final readonly class CreateSearchSyncs
{
    public function __construct(private Algolia $algolia)
    {
        //
    }

    /**
     * Notes that these objects changed, in the caller's transaction, so a change and the note to send it to Algolia are
     * kept or rolled back together. search:sync sends them. Nothing is noted while Algolia isn't set up: search:import
     * fills the index once it is.
     *
     * @param  array<int, string>  $ids
     */
    public function handle(SearchIndex $index, array $ids): void
    {
        if ($ids === [] || ! $this->algolia->indexes()) {
            return;
        }

        DB::table('search_syncs')->insert(array_map(
            fn (string $id): array => ['index' => $index->value, 'object_id' => $id],
            array_values(array_unique($ids)),
        ));
    }
}
