<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\UpdateSearchIndex;
use App\Enums\SearchIndex;
use App\Services\Algolia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('search:sync')]
#[Description('Send the changes waiting for the Algolia indexes, a batch per request')]
final class SyncSearchCommand extends Command
{
    public function handle(Algolia $algolia, UpdateSearchIndex $updateSearchIndex): int
    {
        if (! $algolia->indexes()) {
            $this->components->warn('Algolia is not configured.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach (SearchIndex::cases() as $index) {
            do {
                $batch = $updateSearchIndex->handle($index);
                $sent += $batch;
            } while ($batch === UpdateSearchIndex::BATCH_SIZE);
        }

        $this->components->info("Sent {$sent} changes.");

        return self::SUCCESS;
    }
}
