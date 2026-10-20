<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Section;
use App\Http\Requests\ShowSectionRequest;
use App\Models\User;
use App\Queries\SectionLogQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SectionController
{
    /**
     * Guests read a section like Trending and Latest, but with a generic link preview that search engines do not index.
     */
    public function show(ShowSectionRequest $request, #[CurrentUser] ?User $user, Section $section, SectionLogQuery $query, PageMetadata $metadata): Response
    {
        return Inertia::render('section/show', [
            'section' => ['value' => $section->value, 'title' => $section->title(), 'description' => $section->description()],
            'commits' => Inertia::scroll(fn () => $query->builder($user, $section)->cursorPaginate(20)),
        ])->withViewData(['preview' => $user instanceof User || $request->inertia() ? null : $metadata->section($section)]);
    }
}
