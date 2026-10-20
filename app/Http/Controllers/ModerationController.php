<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Http\Requests\ShowModerationRequest;
use App\Http\Requests\UpdateModerationRequest;
use App\Models\Commit;
use App\Models\User;
use App\Services\CommitTranslations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ModerationController
{
    public function show(ShowModerationRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('moderation/show', [
            'commits' => Inertia::scroll(fn () => Commit::query()
                ->pending()
                ->with([
                    'user',
                    'parent.user',
                    'linkPreview',
                    'images',
                    'quotedCommit' => fn (BelongsTo $query): BelongsTo => $query->withTrashed()->with(['user', 'linkPreview', 'images'])->withPollFor($user),
                ])
                ->withStarsFor($user)
                ->withPollFor($user)
                ->afterQuery(fn (Collection $commits) => resolve(CommitTranslations::class)->attach($commits, includePending: true))
                ->oldest()
                ->oldest('id')
                ->cursorPaginate(20)
                ->through($this->withAiDecision(...))),
        ]);
    }

    /**
     * Approves the selected commits that are still in the queue.
     */
    public function update(UpdateModerationRequest $request, UpdateCommitStatuses $action): RedirectResponse
    {
        /** @var array<int, string> $commitIds */
        $commitIds = $request->validated('commits');

        $action->handle($commitIds, CommitStatus::Approved, onlyPending: true);

        return back();
    }

    /**
     * The AI's decision is hidden everywhere else, but moderators see it.
     */
    private function withAiDecision(Commit $commit): Commit
    {
        return $commit->makeVisible(['ai_decision', 'ai_categories']);
    }
}
