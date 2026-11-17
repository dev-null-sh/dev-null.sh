<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ShownStreams;
use App\Models\Headline;
use App\Models\User;
use App\Queries\HeadlinePageQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class HeadlineController
{
    public function show(Headline $headline, #[CurrentUser] User $user, HeadlinePageQuery $query): Response
    {
        $page = $query->get($headline, $user);
        $commits = $page['commits'];

        return Inertia::render('headline/show', [...$page, 'commits' => fn (): array => ShownStreams::commits($commits())])
            ->withViewData(['robots' => 'noindex, follow']);
    }
}
