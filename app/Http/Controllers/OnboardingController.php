<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserOnboardedAt;
use App\Http\Requests\ShowOnboardingRequest;
use App\Models\User;
use App\Queries\SuggestedUsersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * or take the time the job took. Tom's friendship is made at sign-up too, right before, so bots are left out.
     *
     * @return array{following: Collection<int, User>, followingCount: int, followers: Collection<int, User>, followersCount: int, pendingCount: int}|null
     */
    private function githubImport(ShowOnboardingRequest $request, User $user): ?array
    {
        $importedAt = $user->github_follows_imported_at;

        if ($importedAt === null) {
            return null;
        }

        $imported = fn (BelongsToMany $follows): BelongsToMany => $follows
            ->wherePivotBetween('created_at', [$importedAt->copy()->subSeconds(10), $importedAt])
            ->whereNotIn('users.github_id', User::BOT_GITHUB_IDS);

        return [
            'following' => $imported($user->following())->oldest('users.id')->limit(5)->get(),
            'followingCount' => $imported($user->following())->count(),
            'followers' => $imported($user->followers())->oldest('users.id')->limit(5)->get(),
            'followersCount' => $imported($user->followers())->count(),
            // SKETCH: pending follows aren't stored yet (see the pending_follows table of feat/unclaimed-profiles).
            'pendingCount' => app()->isLocal() ? $request->integer('pending') : 0,
        ];
    }
}
