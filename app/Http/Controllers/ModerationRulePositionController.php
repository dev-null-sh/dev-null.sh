<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateModerationRulePosition;
use App\Http\Requests\UpdateModerationRulePositionRequest;
use App\Models\ModerationRule;
use Illuminate\Http\RedirectResponse;

final readonly class ModerationRulePositionController
{
    public function update(UpdateModerationRulePositionRequest $request, ModerationRule $rule, UpdateModerationRulePosition $action): RedirectResponse
    {
        $action->handle($rule, $request->position());

        return back();
    }
}
