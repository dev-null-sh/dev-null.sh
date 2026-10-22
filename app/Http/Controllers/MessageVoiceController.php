<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowMessageVoiceRequest;
use App\Models\MessageVoice;
use App\Services\EncryptedFiles;
use App\Services\VoiceNotes;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final readonly class MessageVoiceController
{
    public function show(ShowMessageVoiceRequest $request, MessageVoice $messageVoice, VoiceNotes $voiceNotes, EncryptedFiles $encryptedFiles): Response
    {
        $audio = $encryptedFiles->decrypt((string) Storage::disk()->get($messageVoice->path));

        return response($audio, headers: [
            'Content-Type' => $voiceNotes->type($audio),
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
