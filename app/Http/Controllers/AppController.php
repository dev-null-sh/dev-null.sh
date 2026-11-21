<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AppController
{
    public function index(PageMetadata $metadata): Response
    {
        return Inertia::render('app/index')->withViewData(['preview' => $metadata->apps()]);
    }
}
