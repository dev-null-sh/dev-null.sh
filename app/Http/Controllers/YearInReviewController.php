<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Queries\YearInReviewQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class YearInReviewController
{
    public function show(#[CurrentUser] ?User $currentUser, Profile $profile, YearInReviewQuery $review, PageMetadata $metadata, Request $request): Response
    {
        $user = $profile->user;
        $currentYear = (int) now()->year;
        $requestedYear = $request->integer('year');
        $year = $requestedYear > 0 && $requestedYear <= $currentYear ? $requestedYear : $currentYear;

        abort_unless($user instanceof User, 404);

        $parameters = ['profile' => $profile->handle];

        if ($year !== $currentYear) {
            $parameters['year'] = $year;
        }

        $shareUrl = route('wrapped.show', $parameters);

        // Wrapped drops with the notification, every December 3rd at 09:00.
        $unlocksAt = now()->setDate($year, 12, 3)->setTime(9, 0);

        $hasPreviousYear = $user->commits()
            ->whereBetween('commits.created_at', [
                now()->setDate($year - 1, 1, 1)->startOfDay(),
                now()->setDate($year - 1, 12, 31)->endOfDay(),
            ])
            ->exists();

        if (now()->lt($unlocksAt)) {
            return Inertia::render('wrapped/show', [
                'handle' => $profile->handle,
                'name' => $user->name,
                'avatarUrl' => $user->avatar_url,
                'stats' => null,
                'shareUrl' => $shareUrl,
                'locked' => true,
                'unlocksAt' => $unlocksAt->toIso8601String(),
                'year' => $year,
                'currentYear' => $currentYear,
                'hasPreviousYear' => $hasPreviousYear,
            ])->withViewData(['preview' => $metadata->page(
                "{$profile->handle}'s {$year} Wrapped - /dev/null",
                "The {$year} Wrapped for @{$profile->handle} unwraps December 3rd.",
                $shareUrl,
            )]);
        }

        $stats = $review->get($user, $currentUser, $year);

        return Inertia::render('wrapped/show', [
            'handle' => $profile->handle,
            'name' => $user->name,
            'avatarUrl' => $user->avatar_url,
            'stats' => $stats,
            'shareUrl' => $shareUrl,
            'locked' => false,
            'unlocksAt' => null,
            'year' => $year,
            'currentYear' => $currentYear,
            'hasPreviousYear' => $hasPreviousYear,
        ])->withViewData(['preview' => $metadata->page(
            "{$profile->handle}'s {$year} in review - /dev/null",
            "What @{$profile->handle} shipped in {$year}: {$stats['commits']} commits, {$stats['starsEarned']} stars and a {$stats['longestStreak']}-day streak.",
            $shareUrl,
        )]);
    }
}
