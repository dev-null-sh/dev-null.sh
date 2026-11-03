<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        return Inertia::render('headline/show', $query->get($headline, $user))
            ->withViewData(['robots' => 'noindex, follow']);
    }
}
