<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\EvaluationDecision;
use App\Models\CommitEvaluation;

final readonly class AutoModerationStatsQuery
{
    /**
     * What the rules did over the last seven days. Overturned counts automatic decisions a moderator later changed,
     * which is the best sign of whether the rules can be trusted.
     *
     * @return array{approved: int, rejected: int, review: int, failed: int, overturned: int}
     */
    public function get(): array
    {
        $since = now()->subDays(7);

        $count = fn (EvaluationDecision $decision): int => CommitEvaluation::query()
            ->where('created_at', '>=', $since)
            ->where('decision', $decision)
            ->count();

        $overturned = CommitEvaluation::query()
            ->where('commit_evaluations.created_at', '>=', $since)
            ->whereIn('decision', [EvaluationDecision::Approved, EvaluationDecision::Rejected])
            ->join('commits', 'commits.id', '=', 'commit_evaluations.commit_id')
            ->whereColumn('commits.status', '!=', 'commit_evaluations.decision')
            ->count();

        return [
            'approved' => $count(EvaluationDecision::Approved),
            'rejected' => $count(EvaluationDecision::Rejected),
            'review' => $count(EvaluationDecision::Review),
            'failed' => $count(EvaluationDecision::Failed),
            'overturned' => $overturned,
        ];
    }
}
