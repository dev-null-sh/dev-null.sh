<?php

declare(strict_types=1);

namespace App\Services\LinkPreviewProviders;

use App\Contracts\Services\LinkPreviewProvider;

/**
 * Fetches no page, so developing and testing never reach one.
 */
final readonly class NullLinkPreviewProvider implements LinkPreviewProvider
{
    public function metadata(string $url): array
    {
        return ['title' => null, 'description' => null, 'image_url' => null, 'site_name' => null];
    }
}
