<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiGuildIconRequest;
use App\Models\Guild;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class GuildIconController
{
    /**
     * Serves a guild's icon from a signed URL the API handed out, with the web's headers, but cacheable for an hour.
     */
    public function show(ShowApiGuildIconRequest $request, Guild $guild): StreamedResponse
    {
        assert(is_string($guild->icon_path));

        return Storage::disk()->response($guild->icon_path, $guild->handle.'.webp', headers: [
            'Content-Type' => 'image/webp',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], disposition: 'inline');
    }
}
