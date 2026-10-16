import { Form, usePage } from '@inertiajs/react';
import { ChartBar } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import {
    ImagePickerButton,
    ImagePickerPreviews,
    imageErrorFrom,
} from '@/components/commit-image-picker';
import { EmojiSuggestionHint } from '@/components/emoji-suggestion';
import { GraphRail } from '@/components/graph-rail';
import InputError from '@/components/input-error';
import { MentionTextarea } from '@/components/mention-textarea';
import type { PollDraft } from '@/components/poll-editor';
import { emptyPollDraft, PollEditor } from '@/components/poll-editor';
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { moveCaretToEnd, useStash } from '@/hooks/use-stash';
import { completeEmoji, suggestEmoji } from '@/lib/emoji';
import { cn, laneColor } from '@/lib/utils';

const minBodyLength = 3;
const maxBodyLength = 280;

export function CommitComposer() {
    const { auth } = usePage().props;
    const { body, isRestored, updateBody, drop } = useStash(
        auth.user.id,
        'commit',
    );
    const attachments = useImageAttachments();
    const [poll, setPoll] = useState<PollDraft | null>(null);
    const hasImages = attachments.images.length > 0;

    const remaining = maxBodyLength - body.length;
    const isCommittable =
        body.trim().length >= minBodyLength &&
        (poll === null ||
            poll.options.every((option) => option.trim().length > 0));
    const suggestion = suggestEmoji(body);

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Tab' && suggestion !== null) {
            const completed = completeEmoji(body);

            if (completed !== null) {
                event.preventDefault();
                updateBody(completed);
            }

            return;
        }

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
            transform={(data) =>
                attachments.images.length > 0
                    ? { ...data, images: attachments.images }
                    : data
            }
            options={{ preserveScroll: true }}
            onSuccess={(page) => {
                if (!page.flash.committed) {
                    return;
                }

                drop();
                attachments.clear();
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
                            <MentionTextarea
                                id="body"
                                name="body"
                                rows={1}
                                value={body}
                                onValueChange={updateBody}
                                onKeyDown={onKeyDown}
                                onPaste={poll ? undefined : attachments.onPaste}
                                onDragOver={
                                    poll ? undefined : attachments.onDragOver
                                }
                                onDrop={poll ? undefined : attachments.onDrop}
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
                                className="block field-sizing-content min-h-10 w-full resize-none overflow-x-hidden bg-transparent py-1.5 text-[17px] leading-relaxed outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                            />

                            <EmojiSuggestionHint
                                suggestion={suggestion}
                                className="mt-1.5"
                            />

                            <InputError message={errors.body} />

                            <ImagePickerPreviews
                                images={attachments.images}
                                onRemove={attachments.remove}
                            />
                            <InputError
                                message={
                                    attachments.error ?? imageErrorFrom(errors)
                                }
                                className="mt-1"
                            />

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

                            <div className="mt-2 flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                                <div className="mr-auto flex min-w-0 items-center gap-4">
                                    <div className="-ml-2 flex items-center">
                                        <ImagePickerButton
                                            count={attachments.images.length}
                                            disabled={
                                                processing || poll !== null
                                            }
                                            onPick={attachments.attach}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={
                                                processing ||
                                                poll !== null ||
                                                hasImages
                                            }
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
                                            title={
                                                hasImages
                                                    ? 'A commit can have images or a poll, not both'
                                                    : 'Add poll'
                                            }
                                            className="text-muted-foreground"
                                            data-test="add-poll-button"
                                        >
                                            <ChartBar className="size-5" />
                                        </Button>
                                    </div>
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
                                    className="min-w-[7.5rem]"
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
