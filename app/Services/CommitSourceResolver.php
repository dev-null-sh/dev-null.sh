<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitSource;
use Illuminate\Http\Request;

final class CommitSourceResolver
{
    /**
     * A client may identify itself explicitly. Older official clients are recognized by their established product names.
     */
    public function from(Request $request): ?CommitSource
    {
        $source = CommitSource::tryFrom($request->header('X-Devnull-Client', ''));

        if ($source instanceof CommitSource) {
            return $source;
        }

        return match (true) {
            preg_match('/^DevNull-Mac\/[^\s]+$/', $request->userAgent() ?? '') === 1 => CommitSource::Macos,
            preg_match('/^devnull-tui\/[^\s]+$/', $request->userAgent() ?? '') === 1 => CommitSource::Tui,
            default => null,
        };
    }
}
