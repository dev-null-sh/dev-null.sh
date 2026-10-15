<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReopenCommit;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class CommitReopeningController
{
    public function store(Commit $commit, ReopenCommit $action): RedirectResponse
    {
        $action->handle($commit);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Back in the queue.')]);

        return back();
    }
}
