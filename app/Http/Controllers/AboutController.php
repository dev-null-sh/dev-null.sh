<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\ContributorsQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AboutController
{
    public function show(ContributorsQuery $contributors, PageMetadata $metadata, #[CurrentUser] ?User $user): Response
    {
        return Inertia::render('about/show', [
            'contributors' => $contributors->get(...),
            'followingLogins' => fn (): array => $user instanceof User
                ? $user->following()->pluck('username')->map(fn (string $username): string => mb_strtolower($username))->all()
                : [],
        ])->withViewData(['preview' => $metadata->page(
            'About - /dev/null',
            'How /dev/null was built in the open: from the first commit on stream to the developers who joined and the people who shipped it.',
            route('about.show'),
        )]);
    }
}
