<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApiDocsSection;
use App\Enums\ApiErrorCode;
use App\Queries\ApiDocsQuery;
use App\Queries\ApiRateLimitsQuery;
use App\Services\ApiExamples;
use App\Services\ApiGuideSnippets;
use App\Services\PageMetadata;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ApiDocsController
{
    /**
     * A page of the API docs, readable by everyone, with every section for the sidebar, and the endpoints of a group
     * of the reference. The search's index of every endpoint is sent once, and kept by the page across the visit.
     */
    public function show(ApiDocsSection $section, PageMetadata $metadata, ApiDocsQuery $endpoints, ApiExamples $examples, ApiGuideSnippets $snippets, ApiRateLimitsQuery $rateLimits): Response
    {
        return Inertia::render('api-docs/show', [
            'sections' => array_map(fn (ApiDocsSection $section): array => $section->toArray(), ApiDocsSection::ordered()),
            'current' => $section->value,
            'endpoints' => $section->isGuide() ? [] : $endpoints->get($section),
            'index' => Inertia::once(fn (): array => $endpoints->index()),
            'signing' => $section === ApiDocsSection::Authentication ? $examples->signing() : [],
            'rateLimits' => $section === ApiDocsSection::RateLimits ? $rateLimits->get() : [],
            'snippets' => $snippets->for($section),
            'errorCodes' => $section !== ApiDocsSection::Errors ? [] : array_map(fn (ApiErrorCode $code): array => ['code' => $code->value, 'status' => $code->status(), 'meaning' => $code->meaning()], ApiErrorCode::cases()),
        ])->withViewData(['preview' => $metadata->page(
            $section->title().' - API docs - /dev/null',
            $section->description().'. The JSON API the /dev/null apps use, open to anything you build.',
            route('api-docs.show', $section),
        )]);
    }
}
