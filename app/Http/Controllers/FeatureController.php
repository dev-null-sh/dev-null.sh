<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateFeature;
use App\Enums\Feature;
use App\Http\Requests\ListFeaturesRequest;
use App\Http\Requests\UpdateFeatureRequest;
use App\Services\Features;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class FeatureController
{
    public function index(ListFeaturesRequest $request, Features $features): Response
    {
        return Inertia::render('operations/features', [
            'features' => $features->all(...),
        ]);
    }

    public function update(UpdateFeatureRequest $request, Feature $feature, UpdateFeature $action): RedirectResponse
    {
        $action->handle($feature, $request->boolean('enabled'));

        return back();
    }
}
