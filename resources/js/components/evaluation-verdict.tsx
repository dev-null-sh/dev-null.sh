import { router } from '@inertiajs/react';
import { RotateCw, Sparkles, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import CommitEvaluationController from '@/actions/App/Http/Controllers/CommitEvaluationController';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type {
    Commit,
    CommitEvaluation,
    ModerationRuleAction,
    RuleOutcome,
    RuleResult,
} from '@/types';

export const outcomeStyles: Record<RuleOutcome, string> = {
    reject: 'bg-destructive/10 text-destructive border-destructive/30',
    review: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30',
    approve:
        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
    none: 'bg-muted text-muted-foreground border-border',
};

const wouldDo: Record<RuleOutcome, string> = {
    reject: 'would reject',
    review: 'would flag',
    approve: 'would approve',
    none: 'no match',
};

export function percentage(probability: number): number {
    return Math.round(probability * 100);
}

export function thresholdSummary(
    action: ModerationRuleAction,
    act: number | null,
    review: number | null,
): string {
    if (action === 'reject') {
        return `Rejects at ${act}%, sends to review from ${review}%`;
    }

    if (action === 'review') {
        return `Sends to review from ${review}%`;
    }

    return `Approves at ${act}%`;
}

export function decidingRule(
    evaluation: CommitEvaluation,
): RuleResult | undefined {
    const live = evaluation.results.filter(({ mode }) => mode === 'live');
    const outcome = evaluation.decision === 'approved' ? 'approve' : 'reject';

    return live
        .filter((result) => result.outcome === outcome)
        .toSorted((a, b) => b.probability - a.probability)[0];
}

function ResultChip({ result }: { result: RuleResult }) {
    const isTest = result.mode === 'test';
    const isUnmetApproval =
        !isTest && result.action === 'approve' && result.outcome === 'none';

    return (
        <span
            title={`${result.name}: ${percentage(result.probability)}% match. ${thresholdSummary(result.action, result.act_threshold, result.review_threshold)}.`}
            className={cn(
                'inline-flex max-w-full items-center gap-1 rounded-full border px-2 py-0.5 font-mono text-xs',
                outcomeStyles[result.outcome],
                { 'border-dashed bg-transparent': isTest },
            )}
            data-test="evaluation-chip"
        >
            <span className="truncate">
                {isTest && 'Test: '}
                {result.name} {percentage(result.probability)}%
            </span>
            {isTest && (
                <span className="shrink-0">· {wouldDo[result.outcome]}</span>
            )}
            {isUnmetApproval && (
                <span className="shrink-0">
                    · below {result.act_threshold}% auto-approve
                </span>
            )}
        </span>
    );
}

function summary(evaluation: CommitEvaluation, commit: Commit): string {
    const rule = decidingRule(evaluation);

    if (
        (evaluation.decision === 'approved' ||
            evaluation.decision === 'rejected') &&
        commit.status === 'pending'
    ) {
        return `Reopened after Jev ${evaluation.decision} it (${rule?.name ?? 'the rules'})`;
    }

    if (evaluation.decision === 'approved') {
        return `Auto-approved by ${rule?.name ?? 'the rules'}`;
    }

    if (evaluation.decision === 'rejected') {
        return `Auto-rejected by ${rule?.name ?? 'the rules'}`;
    }

    return 'Sent to review by Jev';
}

export function EvaluationVerdict({ commit }: { commit: Commit }) {
    const [isRetrying, setIsRetrying] = useState(false);
    const evaluation = commit.evaluation;

    if (!evaluation) {
        return null;
    }

    if (evaluation.decision === 'failed') {
        return (
            <div
                className="mt-3 flex flex-wrap items-center gap-2 text-sm text-amber-700 dark:text-amber-400"
                data-test="evaluation-failed"
            >
                <TriangleAlert aria-hidden className="size-4" />
                AI check failed, review manually.
                <Button
                    variant="ghost"
                    size="sm"
                    disabled={isRetrying}
                    onClick={() =>
                        router.post(
                            CommitEvaluationController.store(commit),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setIsRetrying(true),
                                onFinish: () => setIsRetrying(false),
                            },
                        )
                    }
                >
                    <RotateCw />
                    {isRetrying ? 'Retrying…' : 'Retry'}
                </Button>
            </div>
        );
    }

    const shown = evaluation.results.filter(
        (result) =>
            result.outcome !== 'none' ||
            (result.mode === 'live' && result.action === 'approve'),
    );
    const testDecisionDiffers =
        evaluation.test_decision !== null &&
        evaluation.test_decision !== evaluation.decision &&
        evaluation.test_decision !== 'review';

    return (
        <div className="mt-3 space-y-2" data-test="evaluation-verdict">
            <p className="flex flex-wrap items-center gap-x-1.5 text-sm text-muted-foreground">
                <Sparkles aria-hidden className="size-3.5" />
                <span>{summary(evaluation, commit)}</span>
                {testDecisionDiffers && (
                    <span>
                        · Test rules would have {evaluation.test_decision} it
                    </span>
                )}
            </p>
            {shown.length > 0 && (
                <ul
                    className="flex flex-wrap gap-1.5"
                    aria-label="Rule results"
                >
                    {shown.map((result) => (
                        <li key={result.rule_id} className="max-w-full">
                            <ResultChip result={result} />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
