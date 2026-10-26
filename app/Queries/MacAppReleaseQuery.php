<?php

declare(strict_types=1);

namespace App\Queries;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Fluent;

/**
 * @phpstan-type MacAppRelease array{version: string, build: int, revision: string, released_at: string, minimum_system_version: string, notarized: bool, size: int, sha256: string, download_url: string, url: string}
 */
final readonly class MacAppReleaseQuery
{
    /**
     * The Mac app the site serves: the DMG in public/downloads, as the release file build.sh writes beside it describes it.
     * Null until both are published.
     *
     * @return MacAppRelease|null
     */
    public function get(): ?array
    {
        $file = public_path('downloads/DevNull.json');

        if (! File::exists($file) || ! File::exists(public_path('downloads/DevNull.dmg'))) {
            return null;
        }

        $release = json_decode(File::get($file), true);

        if (! is_array($release)) {
            return null;
        }

        $release = new Fluent($release);

        return [
            'version' => $release->string('version')->value(),
            'build' => $release->integer('build'),
            'revision' => $release->string('revision')->value(),
            'released_at' => $release->string('released_at')->value(),
            'minimum_system_version' => $release->string('minimum_system_version')->value(),
            'notarized' => $release->boolean('notarized'),
            'size' => $release->integer('size'),
            'sha256' => $release->string('sha256')->value(),
            'download_url' => url('downloads/DevNull.dmg'),
            'url' => route('app.index'),
        ];
    }
}
