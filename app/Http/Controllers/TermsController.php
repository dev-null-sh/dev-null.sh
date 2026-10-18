<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TermsController
{
    public function show(PageMetadata $metadata): Response
    {
        return Inertia::render('terms/show')->withViewData(['preview' => $metadata->page(
            'Terms of service - /dev/null',
            'The rules for using /dev/null: who can sign up, what you can post, what we can do with it, and what we are not responsible for.',
            route('terms.show'),
        )]);
    }
}
