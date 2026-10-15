<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\MoveModerationRule;
use App\Http\Requests\MoveModerationRuleRequest;
use App\Models\ModerationRule;
use Illuminate\Http\RedirectResponse;

final readonly class ModerationRulePositionController
{
    public function update(MoveModerationRuleRequest $request, ModerationRule $rule, MoveModerationRule $action): RedirectResponse
    {
        $action->handle($rule, $request->isUp());

        return back();
    }
}
