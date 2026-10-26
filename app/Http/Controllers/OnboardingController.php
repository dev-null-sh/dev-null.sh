<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserOnboardedAt;
use App\Http\Requests\ShowOnboardingRequest;
use App\Models\User;
use App\Queries\SuggestedUsersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OnboardingController
{
    public function show(ShowOnboardingRequest $request, #[CurrentUser] User $user, SuggestedUsersQuery $suggested): Response
    {
        return Inertia::render('onboarding/show', [
            'preferredLane' => $user->preferredLane(),
            'automaticLane' => $user->automaticLane(),
            'suggestions' => fn (): Collection => $suggested->get($user, limit: 5),
            'githubImport' => fn (): ?array => $this->githubImport($request, $user),
            'commitScriptUrl' => route('cli-script.show'),
        ]);
    }

    /**
     * Finishing and skipping both end the onboarding for good.
     */
    public function update(#[CurrentUser] User $user, UpdateUserOnboardedAt $action): RedirectResponse
    {
        $action->handle($user);

        return redirect()->intended(route('log.show', $user->log, absolute: false));
    }

    /**
     * What the GitHub import brought over: null until the job has run. The follows it made share its timestamp, give
     * or take the time the job took.
     *
     * @return array{following: Collection<int, User>, followingCount: int, followers: Collection<int, User>, followersCount: int, pendingCount: int}|null
     */
    private function githubImport(ShowOnboardingRequest $request, User $user): ?array
    {
        $importedAt = $user->github_follows_imported_at;

        if ($importedAt === null) {
            return null;
        }

        $window = [$importedAt->copy()->subSeconds(10), $importedAt];

        return [
            'following' => $user->following()->wherePivotBetween('created_at', $window)->limit(8)->get(),
            'followingCount' => $user->following()->wherePivotBetween('created_at', $window)->count(),
            'followers' => $user->followers()->wherePivotBetween('created_at', $window)->limit(8)->get(),
            'followersCount' => $user->followers()->wherePivotBetween('created_at', $window)->count(),
            // SKETCH: pending follows aren't stored yet (see the pending_follows table of feat/unclaimed-profiles).
            'pendingCount' => app()->isLocal() ? $request->integer('pending') : 0,
        ];
    }
}
