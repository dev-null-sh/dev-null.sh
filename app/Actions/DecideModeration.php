<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\RuleResult;
use App\Enums\EvaluationDecision;
use App\Enums\ModerationRuleAction;
use App\Enums\RuleOutcome;

final readonly class DecideModeration
{
    /**
     * Reject beats review beats approve, and doubt goes to a moderator: a commit is only approved when every approve
     * rule is sure and no other rule fired, so nothing is approved without an approve rule.
     *
     * @param  list<RuleResult>  $results
     * @return array{decision: EvaluationDecision, rule: RuleResult|null}
     */
    public function handle(array $results): array
    {
        $byProbability = collect($results)->sortByDesc(fn (RuleResult $result): float => $result->probability);

        $rejection = $byProbability->first(fn (RuleResult $result): bool => $result->outcome === RuleOutcome::Reject);

        if ($rejection instanceof RuleResult) {
            return ['decision' => EvaluationDecision::Rejected, 'rule' => $rejection];
        }

        $review = $byProbability->first(fn (RuleResult $result): bool => $result->outcome === RuleOutcome::Review);

        if ($review instanceof RuleResult) {
            return ['decision' => EvaluationDecision::Review, 'rule' => $review];
        }

        $approvals = $byProbability->filter(fn (RuleResult $result): bool => $result->action === ModerationRuleAction::Approve);

        if ($approvals->isNotEmpty() && $approvals->every(fn (RuleResult $result): bool => $result->outcome === RuleOutcome::Approve)) {
            return ['decision' => EvaluationDecision::Approved, 'rule' => $approvals->first()];
        }

        return ['decision' => EvaluationDecision::Review, 'rule' => null];
    }
}
