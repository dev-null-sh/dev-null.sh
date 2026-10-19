<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\LiveUsersQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Inertia\Inertia;
use Inertia\Response;

final readonly class LiveController
{
    public function index(#[CurrentUser] ?User $currentUser, LiveUsersQuery $live, PageMetadata $metadata): Response
    {
        return Inertia::render('live/index', [
            'users' => Inertia::scroll(fn (): CursorPaginator => $live->paginate($currentUser)),
        ])->withViewData(['preview' => $metadata->page(
            'Live - /dev/null',
            'Developers streaming themselves building, right now.',
            route('live.index'),
        )]);
    }
}
