<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateExplorePreference;
use App\Http\Requests\ShowExplorePreferenceRequest;
use App\Http\Requests\UpdateExplorePreferenceRequest;
use App\Models\ExplorePreference;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ExplorePreferenceController
{
    public function show(ShowExplorePreferenceRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('explore/settings', [
            'personalized' => ExplorePreference::query()->find($user->id)?->preferences['personalized'] ?? true,
            'hidden' => DB::table('explore_hidden_topics')->join('explore_topics', 'explore_topics.id', '=', 'topic_id')
                ->where('user_id', $user->id)->orderBy('title')->get(['explore_topics.id', 'kind', 'title']),
        ]);
    }

    public function update(UpdateExplorePreferenceRequest $request, #[CurrentUser] User $user, UpdateExplorePreference $action): RedirectResponse
    {
        $action->handle($user, $request->boolean('personalized'));

        return back();
    }
}
