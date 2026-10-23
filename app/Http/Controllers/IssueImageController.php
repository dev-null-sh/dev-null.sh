<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowIssueImageRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class IssueImageController
{
    public function show(ShowIssueImageRequest $request): StreamedResponse
    {
        return Storage::disk()->response($request->path(), headers: [
            'Content-Type' => 'image/webp',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ], disposition: 'inline');
    }
}
