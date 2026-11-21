<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\LinkPreview;
use App\Services\CanonicalUrls;

final readonly class CreateLinkPreview
{
    public function __construct(private CanonicalUrls $canonicalUrls)
    {
        //
    }

    /**
     * Commits and messages that link to the same page share one preview under its canonical URL, so a page is fetched
     * once however often, and however, it is linked.
     */
    public function handle(string $url): LinkPreview
    {
        $url = $this->canonicalUrls->canonical($url);

        return LinkPreview::query()->firstOrCreate(['url_hash' => hash('sha256', $url)], ['url' => $url]);
    }
}
