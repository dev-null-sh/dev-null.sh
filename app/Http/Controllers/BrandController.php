<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BrandController
{
    public function show(PageMetadata $metadata): Response
    {
        return Inertia::render('brand/show')->withViewData(['preview' => $metadata->page(
            'Brand - /dev/null',
            'The /dev/null logo, mark, wordmark, colors, and backgrounds, ready to download, with notes on how to use them.',
            route('brand.show'),
        )]);
    }
}
