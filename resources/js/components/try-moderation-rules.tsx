import { useHttp } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { useId, useState } from 'react';
import ModerationRulePreviewController from '@/actions/App/Http/Controllers/ModerationRulePreviewController';
import { percentage } from '@/components/evaluation-verdict';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { ModerationPreview, RuleOutcome } from '@/types';

const barStyles: Record<RuleOutcome, string> = {
    reject: 'bg-destructive',
    review: 'bg-amber-500',
    approve: 'bg-emerald-500',
    none: 'bg-muted-foreground/40',
};

const outcomeLabels: Record<RuleOutcome, string> = {
    reject: 'Rejects',
    review: 'Sends to review',
    approve: 'Approves',
    none: 'No action',
};

const textareaClassName =
    'block w-full resize-y rounded-md border bg-transparent px-3 py-2 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:border-destructive';

function decisionText(
    decision: ModerationPreview['decision'],
    rule: string | null,
): string {
    if (decision === 'rejected') {
        return `Rejected by ${rule}`;
    }

    if (decision === 'approved') {
        return `Approved by ${rule}`;
    }

    return rule ? `Sent to review by ${rule}` : 'Sent to review';
}

export function TryModerationRules() {
    const id = useId();
    const [failure, setFailure] = useState('');
    const request = useHttp<
        { body: string; parent_body: string },
        ModerationPreview
    >({ body: '', parent_body: '' });
    const preview = request.response;

    const tryRules = async () => {
        setFailure('');

        try {
            await request.submit(ModerationRulePreviewController.store());
        } catch {
            if (!request.hasErrors) {
                setFailure('Jev could not answer. Try again in a moment.');
            }
        }
    };

    return (
        <section className="px-4 py-6 sm:px-6" aria-labelledby={`${id}-title`}>
            <div id={`${id}-title`}>
                <Heading
                    title="Try it"
                    description="Runs every Test and Live rule on a draft commit. Nothing is stored or moderated."
                />
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    void tryRules();
                }}
                className="space-y-3"
            >
                <div className="space-y-1.5">
                    <Label htmlFor={`${id}-body`}>Commit</Label>
                    <textarea
                        id={`${id}-body`}
                        value={request.data.body}
                        onChange={(event) =>
                            request.setData('body', event.target.value)
                        }
                        maxLength={280}
                        rows={3}
                        aria-invalid={Boolean(request.errors.body)}
                        placeholder="Buy 1,000 GitHub stars for $5, link in bio"
                        className={textareaClassName}
                        data-test="try-body"
                    />
                    <InputError message={request.errors.body} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor={`${id}-parent`}>
                        In reply to{' '}
                        <span className="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </Label>
                    <textarea
                        id={`${id}-parent`}
                        value={request.data.parent_body}
                        onChange={(event) =>
                            request.setData('parent_body', event.target.value)
                        }
                        maxLength={280}
                        rows={2}
                        aria-invalid={Boolean(request.errors.parent_body)}
                        className={textareaClassName}
                        data-test="try-parent"
                    />
                    <InputError message={request.errors.parent_body} />
                </div>
                <Button
                    type="submit"
                    disabled={request.processing || !request.data.body.trim()}
                    data-test="try-button"
                >
                    <FlaskConical />
                    {request.processing ? 'Asking Jev…' : 'Try the rules'}
                </Button>
                <InputError message={failure} />
            </form>

            <div aria-live="polite">
                {preview && !request.processing && (
                    <div
                        className="mt-5 rounded-lg border"
                        data-test="try-results"
                    >
                        <dl className="grid gap-2 border-b px-4 py-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="font-mono text-xs text-muted-foreground">
                                    live rules
                                </dt>
                                <dd
                                    className="font-medium"
                                    data-test="try-decision"
                                >
                                    {decisionText(
                                        preview.decision,
                                        preview.decided_by,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="font-mono text-xs text-muted-foreground">
                                    with test rules live
                                </dt>
                                <dd className="font-medium">
                                    {decisionText(
                                        preview.test_decision,
                                        preview.test_decided_by,
                                    )}
                                </dd>
                            </div>
                        </dl>
                        <ul className="divide-y">
                            {preview.results
                                .toSorted(
                                    (a, b) => b.probability - a.probability,
                                )
                                .map((result) => (
                                    <li
                                        key={result.rule_id}
                                        className="space-y-1.5 px-4 py-2.5"
                                    >
                                        <div className="flex items-baseline gap-2 text-sm">
                                            <span className="min-w-0 truncate">
                                                {result.name}
                                            </span>
                                            {result.mode === 'test' && (
                                                <span className="rounded-full border border-dashed px-1.5 font-mono text-[11px] text-muted-foreground">
                                                    test
                                                </span>
                                            )}
                                            <span className="ml-auto shrink-0 font-mono text-xs text-muted-foreground">
                                                {outcomeLabels[result.outcome]}
                                            </span>
                                            <span className="w-10 shrink-0 text-right font-mono text-sm tabular-nums">
                                                {percentage(result.probability)}
                                                %
                                            </span>
                                        </div>
                                        <div
                                            role="meter"
                                            aria-label={`${result.name} match`}
                                            aria-valuemin={0}
                                            aria-valuemax={100}
                                            aria-valuenow={percentage(
                                                result.probability,
                                            )}
                                            className="h-1.5 overflow-hidden rounded-full bg-muted"
                                        >
                                            <div
                                                className={cn(
                                                    'h-full rounded-full transition-[width] duration-500',
                                                    barStyles[result.outcome],
                                                )}
                                                style={{
                                                    width: `${Math.max(percentage(result.probability), 2)}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                        </ul>
                        <p className="border-t px-4 py-2 font-mono text-xs text-muted-foreground">
                            {preview.model}
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}
