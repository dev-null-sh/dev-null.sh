<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommitUpdate;
use App\Events\CommitsUpdated;
use App\Http\Requests\UpdateLaunchPassengerRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LaunchPassengersQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class LaunchPassengerController
{
    public function store(UpdateLaunchPassengerRequest $request, #[CurrentUser] User $user, Commit $commit, LaunchPassengersQuery $passengers): JsonResponse
    {
        $inserted = $commit->launchPassengers()->newPivotStatement()->insertOrIgnore([
            'commit_id' => $commit->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        if ($inserted > 0) {
            event(new CommitsUpdated([$commit], CommitUpdate::Engagement));
        }

        return response()->json($passengers->get($commit, $user))->header('Cache-Control', 'private, no-store');
    }

    public function destroy(UpdateLaunchPassengerRequest $request, #[CurrentUser] User $user, Commit $commit, LaunchPassengersQuery $passengers): JsonResponse
    {
        if ($commit->launchPassengers()->detach($user) > 0) {
            event(new CommitsUpdated([$commit], CommitUpdate::Engagement));
        }

        return response()->json($passengers->get($commit, $user))->header('Cache-Control', 'private, no-store');
    }
}
