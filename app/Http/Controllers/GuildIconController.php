<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowGuildIconRequest;
use App\Models\Guild;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class GuildIconController
{
    public function show(ShowGuildIconRequest $request, Guild $guild): StreamedResponse
    {
        assert(is_string($guild->icon_path));

        return Storage::disk()->response($guild->icon_path, $guild->handle.'.webp', headers: [
            'Content-Type' => 'image/webp',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], disposition: 'inline');
    }
}
