<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateModerationRule;
use App\Actions\CreateStarterModerationRules;
use App\Actions\DeleteModerationRule;
use App\Actions\UpdateModerationRule;
use App\Enums\ModerationStrictness;
use App\Http\Requests\CreateModerationRuleRequest;
use App\Http\Requests\DeleteModerationRuleRequest;
use App\Http\Requests\ListModerationRulesRequest;
use App\Http\Requests\UpdateModerationRuleRequest;
use App\Models\ModerationRule;
use App\Queries\AutoModerationStatsQuery;
use App\Services\TypeSafe;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ModerationRuleController
{
    public function index(ListModerationRulesRequest $request, TypeSafe $typeSafe, AutoModerationStatsQuery $stats): Response
    {
        return Inertia::render('moderation/rules', [
            'rules' => ModerationRule::query()->ordered()->get(),
            'templates' => CreateStarterModerationRules::TEMPLATES,
            'presets' => ModerationStrictness::presets(),
            'maxRules' => CreateModerationRuleRequest::MAX_RULES,
            'status' => [
                'isConfigured' => $typeSafe->isConfigured(),
                'isEnabled' => $typeSafe->isEnabled(),
                'model' => $typeSafe->model(),
            ],
            'stats' => $stats->get(),
        ]);
    }

    public function store(CreateModerationRuleRequest $request, CreateModerationRule $action): RedirectResponse
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();

        $action->handle($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rule added.')]);

        return back();
    }

    public function update(UpdateModerationRuleRequest $request, ModerationRule $rule, UpdateModerationRule $action): RedirectResponse
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();

        $action->handle($rule, $attributes);

        return back();
    }

    public function destroy(DeleteModerationRuleRequest $request, ModerationRule $rule, DeleteModerationRule $action): RedirectResponse
    {
        $action->handle($rule);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rule deleted.')]);

        return back();
    }
}
