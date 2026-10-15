<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\EvaluateCommit;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;

final readonly class CommitEvaluationController
{
    public function store(Commit $commit, EvaluateCommit $action): RedirectResponse
    {
        $action->handle($commit);

        return back();
    }
}
