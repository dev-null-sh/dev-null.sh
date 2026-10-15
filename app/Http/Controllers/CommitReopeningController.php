<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitReopeningRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitReopeningController
{
    public function store(CreateCommitReopeningRequest $request, Commit $commit, UpdateCommitStatus $action): RedirectResponse
    {
        $action->handle($commit, CommitStatus::Pending);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Back in the queue.')]);

        return back();
    }
}
