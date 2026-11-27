<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowGuildSubmissionIconRequest;
use App\Models\GuildSubmission;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class GuildSubmissionIconController
{
    public function show(ShowGuildSubmissionIconRequest $request, GuildSubmission $submission): StreamedResponse
    {
        $icon = $request->icon();

        return response()->stream(function () use ($icon): void {
            fpassthru($icon);
            fclose($icon);
        }, headers: [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $submission->handle.'.webp', 'guild.webp'),
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
