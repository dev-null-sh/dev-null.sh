<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PrivacyController
{
    public function show(PageMetadata $metadata): Response
    {
        return Inertia::render('privacy/show')->withViewData(['preview' => $metadata->page(
            'Privacy policy - /dev/null',
            'What /dev/null collects, why, who it is shared with, how long it is kept, and how to export or delete all of it.',
            route('privacy.show'),
        )]);
    }
}
