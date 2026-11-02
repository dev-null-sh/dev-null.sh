<?php

declare(strict_types=1);

use App\Models\MessageImage;
use App\Services\EncryptedFiles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Images sent in remotes used to be stored plain. Each one is encrypted in place, keeping its path; one already
     * encrypted, or missing, is left alone, so running it again changes nothing.
     */
    public function up(): void
    {
        $encryptedFiles = resolve(EncryptedFiles::class);

        foreach (MessageImage::query()->select('path')->cursor() as $image) {
            $contents = Storage::disk()->get($image->path);

            if (is_string($contents) && str_starts_with($contents, 'RIFF')) {
                Storage::disk()->put($image->path, $encryptedFiles->encrypt($contents));
            }
        }
    }
};
