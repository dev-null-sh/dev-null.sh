<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitReportImageRequest;
use App\Models\CommitReport;
use App\Services\EncryptedFiles;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final readonly class CommitReportImageController
{
    public function show(ShowCommitReportImageRequest $request, CommitReport $report, int $image, EncryptedFiles $encryptedFiles): Response
    {
        $file = $report->images[$image] ?? null;
        assert(is_array($file));

        return response($encryptedFiles->decrypt((string) Storage::disk()->get($file['path'])), headers: [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
