<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Queries\YearInReviewQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final readonly class YearInReviewController
{
    public function show(#[CurrentUser] ?User $currentUser, Profile $profile, YearInReviewQuery $review, PageMetadata $metadata): Response
    {
        $user = $profile->user;
        $year = (int) now()->year;

        abort_unless($user instanceof User, 404);

        $stats = $review->get($user, $currentUser, $year);

        return Inertia::render('wrapped/show', [
            'handle' => $profile->handle,
            'name' => $user->name,
            'avatarUrl' => $user->avatar_url,
            'stats' => $stats,
            'shareUrl' => route('wrapped.show', ['profile' => $profile->handle]),
        ])->withViewData(['preview' => $metadata->page(
            "{$profile->handle}'s {$year} in review - /dev/null",
            "What @{$profile->handle} shipped in {$year}: {$stats['commits']} commits, {$stats['starsEarned']} stars and a {$stats['longestStreak']}-day streak.",
            route('wrapped.show', ['profile' => $profile->handle]),
        )]);
    }
}
