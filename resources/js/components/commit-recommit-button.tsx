import { Form, usePage } from '@inertiajs/react';
import { Check, Repeat2 } from 'lucide-react';
import { useRef } from 'react';
import { store } from '@/actions/App/Http/Controllers/CommitRecommitController';
import { ActionTooltip } from '@/components/action-tooltip';
import { CommitRulesDialog, useCommitRules } from '@/components/commit-rules';
import { useOwnsCommit } from '@/hooks/use-owns-commit';
import { signIn } from '@/lib/auth';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

export function CommitRecommitButton({
    commit,
    className,
}: {
    commit: Commit;
    className?: string;
}) {
    const { auth } = usePage().props;
    const ownsCommit = useOwnsCommit();
    const commitRules = useCommitRules();
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    if (commit.status !== 'approved' || ownsCommit(commit)) {
        return null;
    }

    const recommitted = commit.recommitted === true;

    return auth.user === null ? (
        <ActionTooltip label="Recommit">
            <button
                type="button"
                onClick={signIn}
                aria-label="Recommit"
                className={cn(
                    'flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                    className,
                )}
                data-test="recommit-button"
            >
                <Repeat2 className="size-4" />
                <span className="sr-only sm:not-sr-only">Recommit</span>
            </button>
        </ActionTooltip>
    ) : (
        <Form
            {...store.form(commit.id)}
            onBefore={() => commitRules.beforeSubmit()}
            transform={(data) => ({
                ...data,
                commit_rules_accepted: commitRules.hasAccepted(),
            })}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <>
                    <ActionTooltip
                        label={recommitted ? 'Recommitted' : 'Recommit'}
                    >
                        <button
                            type="submit"
                            disabled={recommitted || processing}
                            aria-label={
                                recommitted ? 'Recommitted' : 'Recommit'
                            }
                            aria-pressed={recommitted}
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-70',
                                recommitted && 'text-primary',
                                className,
                            )}
                            data-test="recommit-button"
                        >
                            {recommitted ? (
                                <Check className="size-4" />
                            ) : (
                                <Repeat2 className="size-4" />
                            )}
                            <span className="sr-only sm:not-sr-only">
                                {recommitted ? 'Recommitted' : 'Recommit'}
                            </span>
                        </button>
                    </ActionTooltip>
                    <textarea
                        ref={textareaRef}
                        name="body"
                        tabIndex={-1}
                        aria-hidden="true"
                        className="sr-only"
                        readOnly
                        value=""
                    />
                    <CommitRulesDialog
                        rules={commitRules}
                        textarea={textareaRef}
                    />
                </>
            )}
        </Form>
    );
}
