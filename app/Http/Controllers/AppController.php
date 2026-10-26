<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListAppsRequest;
use App\Queries\MacAppReleaseQuery;
use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AppController
{
    public function index(ListAppsRequest $request, MacAppReleaseQuery $releases, PageMetadata $metadata): Response
    {
        $release = $releases->get();

        return Inertia::render('app/index', [
            'release' => $release,
            'isMac' => $request->isMac(),
        ])->withViewData(['preview' => $metadata->apps($release)]);
    }
}
