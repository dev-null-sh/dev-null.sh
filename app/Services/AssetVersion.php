<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

final readonly class AssetVersion
{
    /**
     * Inertia's asset version, available even when its HTTP middleware has not run.
     */
    public function get(): ?string
    {
        if (is_string($assetUrl = config('app.asset_url')) && $assetUrl !== '') {
            return hash('xxh128', $assetUrl);
        }

        if (file_exists($manifest = public_path('build/manifest.json'))) {
            return hash('xxh128', File::get($manifest));
        }

        if (file_exists($manifest = public_path('mix-manifest.json'))) {
            return hash('xxh128', File::get($manifest));
        }

        return null;
    }
}
