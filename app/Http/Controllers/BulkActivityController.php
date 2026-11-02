<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListBulkActivityRequest;
use App\Models\User;
use App\Queries\BulkActivityQuery;
use App\Queries\UserModerationQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BulkActivityController
{
    /**
     * The accounts acting in bulk, so moderators can tell a member who answers newcomers from an account working
     * through every signup, and put them in timeout or ban them from the list. Admins have no moderation menu, as on
     * their profile.
     */
    public function index(ListBulkActivityRequest $request, BulkActivityQuery $query, UserModerationQuery $moderation): Response
    {
        $activity = $query->get($request->days());
        $users = collect($activity)
            ->flatMap(fn (array $accounts): array => array_column($accounts, 'user'))
            ->unique('id')
            ->reject(fn (User $user): bool => $user->isAdmin());

        return Inertia::render('moderation/bulk', [
            'scale' => $request->scale(),
            'activity' => $activity,
            'thresholds' => $query->thresholds($request->days()),
            'moderation' => $moderation->get($users),
        ]);
    }
}
