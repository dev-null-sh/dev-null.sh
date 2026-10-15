import { Form, usePage } from '@inertiajs/react';
import { ChartBar } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { GraphRail } from '@/components/graph-rail';
import InputError from '@/components/input-error';
import type { PollDraft } from '@/components/poll-editor';
import { emptyPollDraft, PollEditor } from '@/components/poll-editor';
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { moveCaretToEnd, useStash } from '@/hooks/use-stash';
import { emojifyInput } from '@/lib/emoji';
import { cn, laneColor } from '@/lib/utils';

const minBodyLength = 3;
const maxBodyLength = 280;

export function CommitComposer() {
    const { auth } = usePage().props;
    const { body, isRestored, updateBody, drop } = useStash(
        auth.user.id,
        'commit',
    );

    const [poll, setPoll] = useState<PollDraft | null>(null);

    const remaining = maxBodyLength - body.length;
    const isCommittable =
        body.trim().length >= minBodyLength &&
        (poll === null ||
            poll.options.every((option) => option.trim().length > 0));

    const submitOnModEnter = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key === 'Enter' &&
            (event.metaKey || event.ctrlKey) &&
            isCommittable
        ) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    };

    return (
        <Form
            {...CommitController.store.form()}
            options={{ preserveScroll: true }}
            onSuccess={(page) => {
                if (!page.flash.committed) {
                    return;
                }

                drop();
                setPoll(null);
                requestAnimationFrame(() =>
                    document.getElementById('body')?.focus(),
                );
            }}
            className="flex"
        >
            {({ processing, errors }) => (
                <>
                    <GraphRail
                        lane={laneColor(auth.user.lane)}
                        variant="staged"
                        isFilled={isCommittable}
                    />

                    <div className="flex min-w-0 flex-1 gap-3 border-b py-3 pr-4">
                        <UserAvatar
                            user={auth.user}
                            className="hidden size-10 sm:flex"
                        />

                        <div className="min-w-0 flex-1">
                            <label htmlFor="body" className="sr-only">
                                Body
                            </label>
                            <textarea
                                id="body"
                                name="body"
                                rows={1}
                                value={body}
                                onChange={(event) => {
                                    const target = event.currentTarget;
                                    const { text, caret } = emojifyInput(
                                        target.value,
                                        target.selectionStart ??
                                            target.value.length,
                                    );

                                    updateBody(text);

                                    if (text !== target.value) {
                                        requestAnimationFrame(() =>
                                            target.setSelectionRange(
                                                caret,
                                                caret,
                                            ),
                                        );
                                    }
                                }}
                                onKeyDown={submitOnModEnter}
                                onFocus={
                                    isRestored ? moveCaretToEnd : undefined
                                }
                                maxLength={maxBodyLength}
                                placeholder={
                                    poll
                                        ? 'Ask a question'
                                        : 'What did you ship?'
                                }
                                autoComplete="off"
                                aria-invalid={errors.body ? true : undefined}
                                aria-describedby="body-count"
                                className="block field-sizing-content min-h-10 w-full resize-none bg-transparent py-1.5 text-[17px] leading-relaxed outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                            />

                            <InputError message={errors.body} />

                            {poll && (
                                <PollEditor
                                    poll={poll}
                                    errors={errors}
                                    onChange={setPoll}
                                    onRemove={() => {
                                        setPoll(null);
                                        document
                                            .getElementById('body')
                                            ?.focus();
                                    }}
                                />
                            )}

                            <div className="mt-2 flex items-center justify-end gap-4">
                                <div className="mr-auto flex min-w-0 items-center gap-3">
                                    {poll === null && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setPoll(emptyPollDraft);
                                                requestAnimationFrame(() =>
                                                    document
                                                        .getElementById(
                                                            'poll-option-0',
                                                        )
                                                        ?.focus(),
                                                );
                                            }}
                                            aria-label="Add poll"
                                            title="Add poll"
                                            className="-ml-1.5 rounded-md p-1.5 text-primary transition-colors hover:bg-primary-soft focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            data-test="add-poll-button"
                                        >
                                            <ChartBar className="size-5" />
                                        </button>
                                    )}
                                    {isRestored && (
                                        <StashLabel
                                            onDrop={() => {
                                                drop();
                                                document
                                                    .getElementById('body')
                                                    ?.focus();
                                            }}
                                            className="text-[13px]"
                                        />
                                    )}
                                </div>
                                <span
                                    id="body-count"
                                    aria-label={`${remaining} characters left`}
                                    className={cn(
                                        'font-mono text-[13px] text-muted-foreground',
                                        {
                                            'text-amber-700 dark:text-amber-400':
                                                remaining <= 20,
                                            'text-destructive': remaining === 0,
                                        },
                                    )}
                                >
                                    {remaining}
                                </span>
                                <Button
                                    type="submit"
                                    disabled={processing || !isCommittable}
                                    title="Commit (⌘/Ctrl + Enter)"
                                    data-test="create-commit-button"
                                >
                                    {processing ? 'Committing…' : 'Commit'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}
