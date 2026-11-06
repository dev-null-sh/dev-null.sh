<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommit;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRecommitRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\UserAchievementsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitRecommitController
{
    public function store(CreateCommitRecommitRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommit $action, UserAchievementsQuery $achievements): RedirectResponse
    {
        $before = $achievements->unlockedBadgeNames($user);
        $recommit = $action->handle($user, '', quotedCommit: $commit, linkPreview: false);
        $unlocked = $achievements->unlockedBadgeNames($user)->diff($before)->values();

        if ($unlocked->isNotEmpty()) {
            Inertia::flash('achievementsUnlocked', $unlocked->all());
        }

        if ($recommit->status === CommitStatus::Pending) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'Your recommit is pending review. Everyone will see it once it is approved.',
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Recommitted.',
            ]);
        }

        return back();
    }
}
