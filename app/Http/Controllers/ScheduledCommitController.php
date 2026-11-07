<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateScheduledCommit;
use App\Actions\DeleteScheduledCommit;
use App\Enums\CommitSource;
use App\Enums\UserUpdate;
use App\Http\Requests\CreateScheduledCommitRequest;
use App\Http\Requests\DeleteScheduledCommitRequest;
use App\Http\Resources\ScheduledCommitResource;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Queries\ScheduledCommitsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ScheduledCommitController
{
    public function index(Request $request, #[CurrentUser] User $user, ScheduledCommitsQuery $scheduled): Response
    {
        return Inertia::render('scheduled-commits/index', [
            'scheduledCommits' => Inertia::live(fn (): LengthAwarePaginator => $scheduled->builder($user)
                ->paginate(20)->through(fn (ScheduledCommit $scheduledCommit): array => ScheduledCommitResource::make($scheduledCommit)->resolve($request)), on: UserUpdate::ScheduledCommits->value, channel: new PrivateChannel($user)),
        ]);
    }

    /**
     * Schedules a post with the current app source so it can retain that source when published later.
     */
    public function store(CreateScheduledCommitRequest $request, #[CurrentUser] User $user, CreateScheduledCommit $action): RedirectResponse
    {
        $action->handle($user, $request->string('body')->value(), CarbonImmutable::parse($request->string('scheduled_at')->value())->utc(), $request->images(), $request->imageDescriptions(), $request->poll(), $request->linkPreview(), $request->organization(), source: CommitSource::forRequest($request));

        Inertia::flash('scheduled', true);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Commit scheduled. Only you can see it until it is submitted.']);

        return back();
    }

    public function destroy(DeleteScheduledCommitRequest $request, #[CurrentUser] User $user, ScheduledCommit $scheduledCommit, DeleteScheduledCommit $action): RedirectResponse
    {
        $canceled = $action->handle($user, $scheduledCommit);

        Inertia::flash('toast', ['type' => $canceled ? 'success' : 'info', 'message' => $canceled
            ? 'Scheduled commit canceled.'
            : 'This commit was already submitted or canceled. Refresh to see its status.']);

        return back();
    }
}
