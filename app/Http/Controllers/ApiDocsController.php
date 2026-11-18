<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApiDocsSection;
use App\Services\ApiGuideSnippets;
use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ApiDocsController
{
    /**
     * A page of the API docs, readable by everyone, with every section for the sidebar.
     */
    public function show(ApiDocsSection $section, PageMetadata $metadata, ApiGuideSnippets $snippets): Response
    {
        return Inertia::render('api-docs/show', [
            'sections' => array_map(fn (ApiDocsSection $section): array => $section->toArray(), ApiDocsSection::cases()),
            'current' => $section->value,
            'snippets' => $snippets->for($section),
        ])->withViewData(['preview' => $metadata->page(
            $section->title().' - API docs - /dev/null',
            $section->description().'. The JSON API the /dev/null apps use, open to anything you build.',
            route('api-docs.show', $section),
        )]);
    }
}
