<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PreviewModerationRules;
use App\Http\Requests\PreviewModerationRulesRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use UnexpectedValueException;

final readonly class ModerationRulePreviewController
{
    public function store(PreviewModerationRulesRequest $request, PreviewModerationRules $action): JsonResponse
    {
        try {
            return response()->json($action->handle($request->body(), $request->parentBody()));
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            report($exception);

            return response()->json(['message' => __('Jev could not answer. Try again in a moment.')], 503);
        }
    }
}
