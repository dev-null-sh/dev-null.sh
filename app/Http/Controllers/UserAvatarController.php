<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowUserAvatarRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class UserAvatarController
{
    public function show(ShowUserAvatarRequest $request, User $user): StreamedResponse
    {
        $path = $user->avatar_upload_path;

        if (! is_string($path)) {
            abort(404);
        }

        return Storage::disk()->response($path, 'avatar.webp', headers: [
            'Content-Type' => 'image/webp',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
