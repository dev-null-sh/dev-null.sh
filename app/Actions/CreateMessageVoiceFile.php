<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\EncryptedFiles;
use App\Services\VoiceNotes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CreateMessageVoiceFile
{
    public function __construct(private VoiceNotes $voiceNotes, private EncryptedFiles $encryptedFiles)
    {
        //
    }

    /**
     * The recording is rebuilt from its audio alone, so nothing that came along with it is kept, and stored encrypted.
     * It is erased again if the transaction saving it rolls back. The waveform is drawn by the browser, which heard the
     * recording; the server never decodes it.
     *
     * @param  list<int>  $waveform
     * @return array{path: string, duration_ms: int, waveform: list<int>}
     */
    public function handle(UploadedFile $voice, array $waveform): array
    {
        $rebuilt = $this->voiceNotes->rebuild((string) $voice->get()) ?? throw new RuntimeException('The voice note is not one.');

        $path = 'message-voices/'.Str::uuid7()->toString();

        if (! Storage::disk()->put($path, $this->encryptedFiles->encrypt($rebuilt['file']))) {
            throw new RuntimeException('The voice note could not be stored.');
        }

        DB::afterRollBack(fn (): bool => Storage::disk()->delete($path));

        return [
            'path' => $path,
            'duration_ms' => $rebuilt['duration_ms'],
            'waveform' => $waveform,
        ];
    }
}
