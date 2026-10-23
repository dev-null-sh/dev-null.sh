<?php

declare(strict_types=1);

use App\Services\EncryptedFiles;
use App\Services\VoiceNotes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Voice notes were first kept with a plain duration, and as WebM encrypted with Crypt. The duration is encrypted,
     * and each file is rebuilt as MP4, which every browser plays, and encrypted as private files now are. A note
     * already moved over is left alone, so running it again changes nothing.
     */
    public function up(): void
    {
        Schema::table('message_voices', function (Blueprint $table): void {
            $table->text('duration_ms')->change();
        });

        $encryptedFiles = resolve(EncryptedFiles::class);
        $voiceNotes = resolve(VoiceNotes::class);

        foreach (DB::table('message_voices')->select(['id', 'path', 'duration_ms'])->cursor() as $voice) {
            if (is_numeric($voice->duration_ms)) {
                DB::table('message_voices')->where('id', $voice->id)->update(['duration_ms' => Crypt::encryptString((string) $voice->duration_ms)]);
            }

            $stored = is_string($voice->path) ? Storage::disk()->get($voice->path) : null;

            if (is_string($stored) && str_starts_with($stored, 'eyJ')) {
                $audio = Crypt::decryptString($stored);

                Storage::disk()->put((string) $voice->path, $encryptedFiles->encrypt($voiceNotes->rebuild($audio)['file'] ?? $audio));
            }
        }
    }
};
