<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateSearchSyncs;
use App\Enums\SearchIndex;
use App\Models\User;
use App\Services\Algolia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('search:import {index : The index to fill: users}')]
#[Description('Configure an Algolia index and queue every record for search:sync to send')]
final class ImportSearchCommand extends Command
{
    private const int CHUNK_SIZE = 1000;

    public function handle(Algolia $algolia, CreateSearchSyncs $createSearchSyncs): int
    {
        $name = $this->argument('index');
        $index = is_string($name) ? SearchIndex::tryFrom($name) : null;

        if (! $index instanceof SearchIndex) {
            $this->components->error('Unknown index. Try: '.implode(', ', array_column(SearchIndex::cases(), 'value')).'.');

            return self::FAILURE;
        }

        if (! $algolia->indexes()) {
            $this->components->error('Set ALGOLIA_APP_ID and ALGOLIA_WRITE_KEY first.');

            return self::FAILURE;
        }

        $algolia->configure($index);

        $queued = 0;

        $records = match ($index) {
            SearchIndex::Users => User::query(),
        };

        $records->select('id')->chunkById(self::CHUNK_SIZE, function (Collection $records) use ($createSearchSyncs, $index, &$queued): void {
            $createSearchSyncs->handle($index, $records->modelKeys());

            $queued += $records->count();
        });

        $this->components->info("Queued {$queued} records for {$algolia->name($index)}. search:sync sends them.");

        return self::SUCCESS;
    }
}
