<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowEventCoverRequest;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class EventCoverController
{
    public function show(ShowEventCoverRequest $request, Event $event): StreamedResponse
    {
        return Storage::disk()->response((string) $event->cover_path, headers: [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
