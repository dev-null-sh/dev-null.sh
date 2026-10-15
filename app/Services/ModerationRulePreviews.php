<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\RuleResult;
use App\Models\ModerationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use UnexpectedValueException;

final readonly class ModerationRulePreviews
{
    public function __construct(
        private Jev $jev,
        private ModerationDecisions $moderationDecisions,
    ) {
        //
    }

    /**
     * Shows what the live and test rules make of a draft commit, without storing or moderating anything.
     *
     * @return array{decision: string, decided_by: string|null, test_decision: string, test_decided_by: string|null, model: string, results: list<array<string, mixed>>}
     *
     * @throws ConnectionException|RequestException|UnexpectedValueException
     */
    public function for(string $body, ?string $parentBody = null): array
    {
        $run = $this->jev->run(ModerationRule::query()->active()->ordered()->get(), $body, $parentBody);

        $live = $this->moderationDecisions->decide($run->liveResults());
        $all = $this->moderationDecisions->decide($run->results);

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
