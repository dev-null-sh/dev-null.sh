<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\ModerationRuleAction;
use App\Enums\ModerationRuleMode;
use App\Enums\RuleOutcome;
use App\Models\ModerationRule;

final readonly class RuleResult
{
    public function __construct(
        public string $ruleId,
        public string $name,
        public ModerationRuleAction $action,
        public ModerationRuleMode $mode,
        public float $probability,
        public RuleOutcome $outcome,
        public ?int $actThreshold,
        public ?int $reviewThreshold,
        public ?string $rejectionMessage,
    ) {
        //
    }

    public static function for(ModerationRule $rule, float $probability): self
    {
        ['act' => $act, 'review' => $review] = $rule->thresholds();

        return new self(
            $rule->id,
            $rule->name,
            $rule->action,
            $rule->mode,
            $probability,
            $rule->outcomeFor($probability),
            $act,
            $review,
            $rule->rejection_message,
        );
    }

    /**
     * A snapshot of the rule as it was when it judged the commit, so later edits don't rewrite history.
     *
     * @return array{rule_id: string, name: string, action: string, mode: string, probability: float, outcome: string, act_threshold: int|null, review_threshold: int|null}
     */
    public function toArray(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'name' => $this->name,
            'action' => $this->action->value,
            'mode' => $this->mode->value,
            'probability' => $this->probability,
            'outcome' => $this->outcome->value,
            'act_threshold' => $this->actThreshold,
            'review_threshold' => $this->reviewThreshold,
        ];
    }
}
