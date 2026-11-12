<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Reads the Open Graph metadata of the page behind a link someone shared.
 *
 * @phpstan-type Metadata array{title: string|null, description: string|null, image_url: string|null, site_name: string|null}
 */
interface LinkPreviewProvider
{
    /**
     * The page's metadata, all null when the page can't or mustn't be fetched. Failures that may pass, such as
     * connection errors, timeouts, rate limits and server errors, throw an Illuminate\Http\Client\HttpClientException
     * instead, so the caller can try again later.
     *
     * @return Metadata
     */
    public function metadata(string $url): array;
}
