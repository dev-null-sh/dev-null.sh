<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final readonly class DeleteStoredFiles
{
    /**
     * Call inside the transaction removing the file metadata. The cleanup survives a crash after commit,
     * and rolls back with the metadata if deletion of the account fails.
     *
     * @param  array<int, string>  $paths
     */
    public function retain(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $id = DB::table('pending_file_deletions')->insertGetId([
            'disk' => config()->string('filesystems.default'),
            'paths' => json_encode(array_values(array_unique($paths)), JSON_THROW_ON_ERROR),
        ]);

        DB::afterCommit(fn () => $this->handle($id));
    }

    public function handle(int $id): void
    {
        $pending = DB::table('pending_file_deletions')->find($id);

        if ($pending === null) {
            return;
        }

        try {
            /** @var list<string> $paths */
            $paths = json_decode($pending->paths, true, flags: JSON_THROW_ON_ERROR);

            if (! Storage::disk($pending->disk)->delete($paths)) {
                throw new RuntimeException('File cleanup failed for pending deletion '.$id);
            }

            DB::table('pending_file_deletions')->where('id', $id)->delete();
        } catch (Throwable $exception) {
            // Keep the durable record for the scheduled retry, including when the disk throws.
            report($exception);
        }
    }
}
