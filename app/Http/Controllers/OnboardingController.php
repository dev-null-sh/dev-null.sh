<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserOnboardedAt;
use App\Enums\Log;
use App\Http\Requests\ShowOnboardingRequest;
use App\Models\User;
use App\Queries\SuggestedUsersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OnboardingController
{
    public function show(ShowOnboardingRequest $request, #[CurrentUser] User $user, SuggestedUsersQuery $suggested): Response
    {
        return Inertia::render('onboarding/show', [
            'preferredLane' => $user->preferred_lane,
            'automaticLane' => $user->automaticLane(),
            'suggestions' => $suggested->get($user, limit: 5),
            'commitScriptUrl' => route('cli-script.show'),
        ]);
    }

    /**
     * Finishing and skipping both end the onboarding for good.
     */
    public function update(#[CurrentUser] User $user, UpdateUserOnboardedAt $action): RedirectResponse
    {
        $action->handle($user);

        return redirect()->intended(route('log.show', Log::Following, absolute: false));
    }
}
