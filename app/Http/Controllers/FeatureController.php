<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateFeature;
use App\Enums\Feature;
use App\Http\Requests\UpdateFeatureRequest;
use Illuminate\Http\RedirectResponse;

final readonly class FeatureController
{
    public function update(UpdateFeatureRequest $request, Feature $feature, UpdateFeature $action): RedirectResponse
    {
        $action->handle($feature, $request->boolean('enabled'));

        return back();
    }
}
