<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\ContributorsQuery;
use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AboutController
{
    public function show(ContributorsQuery $contributors, PageMetadata $metadata): Response
    {
        return Inertia::render('about/show', [
            'contributors' => $contributors->get(...),
        ])->withViewData(['preview' => $metadata->page(
            'About - /dev/null',
            'How /dev/null was built in the open: from the first commit on stream to the developers who joined and the people who shipped it.',
            route('about.show'),
        )]);
    }
}
