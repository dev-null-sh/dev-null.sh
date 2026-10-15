<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateStarterModerationRules;
use App\Http\Requests\CreateStarterModerationRuleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class StarterModerationRuleController
{
    public function store(CreateStarterModerationRuleRequest $request, CreateStarterModerationRules $action): RedirectResponse
    {
        $action->handle();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Starter rules added in Test mode.')]);

        return back();
    }
}
