<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitSeries;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitSeriesRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitSeriesController
{
    public function store(CreateCommitSeriesRequest $request, #[CurrentUser] User $user, CreateCommitSeries $action): RedirectResponse
    {
        $first = $action->handle($user, $request->bodies(), $request->images(), $request->linkPreviews())->first();

        assert($first instanceof Commit);

        Inertia::flash('committed', [
            'id' => $first->id,
            'body' => $first->body,
            'series_size' => $first->series_size,
        ]);

        if ($first->status === CommitStatus::Pending) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Your series is pending review. Everyone will see it once it is approved.'),
            ]);
        }

        return back();
    }
}
