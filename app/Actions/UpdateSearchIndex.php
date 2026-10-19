<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SearchIndex;
use App\Models\User;
use App\Services\Algolia;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSearchIndex
{
    public const int BATCH_SIZE = 1000;

    public function __construct(private Algolia $algolia)
    {
        //
    }

    /**
     * Sends the oldest changes waiting for the index in one request, and returns how many were sent. Each object is read
     * again rather than replayed, so one changed many times is sent once and one no longer there is removed.
     *
     * Only the notes read are forgotten, by id: a change noted meanwhile, even by a transaction that took its id earlier
     * and committed later, is still waiting for the next run.
     */
    public function handle(SearchIndex $index): int
    {
        $syncs = DB::table('search_syncs')
            ->where('index', $index->value)
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get(['id', 'object_id']);

        if ($syncs->isEmpty()) {
            return 0;
        }

        /** @var list<string> $ids */
        $ids = $syncs->pluck('object_id')->unique()->values()->all();
        $records = $this->records($index, $ids);

        $this->algolia->batch($index, array_values($records), array_values(array_diff($ids, array_keys($records))));

        DB::table('search_syncs')->whereIn('id', $syncs->pluck('id'))->delete();

        return $syncs->count();
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>>
     */
    private function records(SearchIndex $index, array $ids): array
    {
        return match ($index) {
            SearchIndex::Users => User::query()->whereKey($ids)->get(['id', 'username', 'name'])
                ->mapWithKeys(fn (User $user): array => [$user->id => [
                    'objectID' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                ]])
                ->all(),
        };
    }
}
