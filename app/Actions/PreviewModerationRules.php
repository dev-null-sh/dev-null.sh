<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\RuleResult;
use App\Models\ModerationRule;
use App\Services\TypeSafe;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

final readonly class PreviewModerationRules
{
    public function __construct(
        private TypeSafe $typeSafe,
        private RunModerationRules $runModerationRules,
        private DecideModeration $decideModeration,
    ) {
        //
    }

    /**
     * Shows what the live and test rules make of a draft commit, without storing or moderating anything.
     *
     * @return array{decision: string, decided_by: string|null, test_decision: string, test_decided_by: string|null, model: string, results: list<array<string, mixed>>}
     *
     * @throws ConnectionException|RequestException|UnexpectedValueException|ValidationException
     */
    public function handle(string $body, ?string $parentBody = null): array
    {
        if (! $this->typeSafe->isConfigured()) {
            throw ValidationException::withMessages(['body' => __('Set TYPESAFE_API_KEY to try the rules.')]);
        }

        $rules = ModerationRule::query()->active()->ordered()->get();

        if ($rules->isEmpty()) {
            throw ValidationException::withMessages(['body' => __('Put a rule in Test or Live mode to try it.')]);
        }

        $run = $this->runModerationRules->handle($rules, $body, $parentBody);

        $live = $this->decideModeration->handle($run->liveResults());
        $all = $this->decideModeration->handle($run->results);

        return [
            'decision' => $live['decision']->value,
            'decided_by' => $live['rule']?->name,
            'test_decision' => $all['decision']->value,
            'test_decided_by' => $all['rule']?->name,
            'model' => $run->model,
            'results' => array_map(fn (RuleResult $result): array => $result->toArray(), $run->results),
        ];
    }
}
