<?php

declare(strict_types=1);

use App\Actions\DeleteStoredFiles;
use App\Jobs\EmbedKnowledgeBase;
use App\Jobs\EndLiveStreams;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('commits:submit-scheduled')->everyMinute()->withoutOverlapping(5);
Schedule::job(new EmbedKnowledgeBase())->hourly();
Schedule::job(new EndLiveStreams())->everyFiveMinutes();

Schedule::call(function (): void {
    DB::table('pending_file_deletions')->orderBy('id')->chunkById(100, function (Collection $pending): void {
        foreach ($pending as $deletion) {
            throw_if(! isset($deletion->id), UnexpectedValueException::class, 'Invalid pending file deletion record.');

            $id = $deletion->id;
            throw_if(! is_int($id) && (! is_string($id) || ! ctype_digit($id)), UnexpectedValueException::class, 'Invalid pending file deletion ID.');

            resolve(DeleteStoredFiles::class)->handle((int) $id);
        }
    });
})->name('retry-file-deletions')->everyMinute()->withoutOverlapping(5);

Schedule::call(function (): void {
    $store = 'cache.stores.'.config()->string('cache.default');

    if (config()->string($store.'.driver') === 'database') {
        /** @var string|null $connection */
        $connection = config($store.'.connection');

        DB::connection($connection)->table(config()->string($store.'.table'))
            ->where('expiration', '<=', now()->getTimestamp())
            ->delete();
    }
})->name('prune-expired-cache')->hourly();
