import { Form, Link, usePage } from '@inertiajs/react';
import { Clock3 } from 'lucide-react';
import { useState, type KeyboardEvent } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import ScheduledCommitController from '@/actions/App/Http/Controllers/ScheduledCommitController';
import {
    ImagePickerButton,
    ImagePickerPreviews,
    imageErrorFrom,
} from '@/components/commit-image-picker';
import { EmojiSuggestionHint } from '@/components/emoji-suggestion';
import { GraphRail } from '@/components/graph-rail';
import InputError from '@/components/input-error';
import { MentionTextarea } from '@/components/mention-textarea';
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { UserAvatar } from '@/components/user-avatar';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { moveCaretToEnd, useStash } from '@/hooks/use-stash';
import { completeEmoji, suggestEmoji } from '@/lib/emoji';
import { cn, laneColor } from '@/lib/utils';

const minBodyLength = 3;
const maxBodyLength = 280;

export function CommitComposer() {
    const [isScheduling, setIsScheduling] = useState(false);
    const [scheduledAt, setScheduledAt] = useState('');
    const [timezone, setTimezone] = useState('');
    const { auth } = usePage().props;
    const { body, isRestored, updateBody, drop } = useStash(
        auth.user.id,
        'commit',
    );
    const attachments = useImageAttachments();

    const remaining = maxBodyLength - body.length;
    const isCommittable = body.trim().length >= minBodyLength;
    const hasImages = attachments.images.length > 0;
    const isSchedulingImages = isScheduling && hasImages;
    const isSubmittable = isCommittable && !isSchedulingImages;
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
            isSubmittable
        ) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    };

    return (
        <Form
            {...(isScheduling
                ? ScheduledCommitController.store.form()
                : CommitController.store.form())}
            transform={(data) =>
                isScheduling
                    ? {
                          ...data,
                          scheduled_at: new Date(scheduledAt)
                              .toISOString()
                              .replace(/\.\d{3}Z$/, '+00:00'),
                      }
                    : hasImages
                      ? { ...data, images: attachments.images }
                      : data
            }
            options={{ preserveScroll: true }}
            onSuccess={(page) => {
                if (!page.flash.committed && !page.flash.scheduled) {
                    return;
                }

                drop();
                setScheduledAt('');
                setIsScheduling(false);
                attachments.clear();
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
                                onPaste={attachments.onPaste}
                                onDragOver={attachments.onDragOver}
                                onDrop={attachments.onDrop}
                                onFocus={
                                    isRestored ? moveCaretToEnd : undefined
                                }
                                maxLength={maxBodyLength}
                                placeholder="What did you ship?"
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
                                    attachments.error ??
                                    (isSchedulingImages
                                        ? "Images can't be scheduled yet. Remove them to schedule this commit."
                                        : imageErrorFrom(errors))
                                }
                                className="mt-1"
                            />

                            {isScheduling && (
                                <div
                                    id="commit-schedule"
                                    className="mt-3 space-y-2 rounded-lg border bg-muted/30 p-3"
                                >
                                    <label
                                        htmlFor="scheduled-at"
                                        className="text-sm font-medium"
                                    >
                                        Submit at
                                    </label>
                                    <Input
                                        id="scheduled-at"
                                        type="datetime-local"
                                        required
                                        value={scheduledAt}
                                        onChange={(event) => {
                                            const value = event.target.value;
                                            setScheduledAt(value);
                                            const date = new Date(value);
                                            const pad = (n: number) =>
                                                String(n).padStart(2, '0');
                                            const local = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
                                            event.target.setCustomValidity(
                                                value && local !== value
                                                    ? 'This time does not exist in your timezone. Choose another time.'
                                                    : '',
                                            );
                                        }}
                                        aria-describedby="schedule-help schedule-error"
                                        aria-invalid={
                                            errors.scheduled_at
                                                ? true
                                                : undefined
                                        }
                                    />
                                    <p
                                        id="schedule-help"
                                        className="text-xs leading-relaxed text-muted-foreground"
                                    >
                                        {timezone}
                                        {scheduledAt &&
                                        !Number.isNaN(
                                            new Date(scheduledAt).getTime(),
                                        )
                                            ? ` (${new Intl.DateTimeFormat(undefined, { timeZoneName: 'shortOffset' }).formatToParts(new Date(scheduledAt)).find((part) => part.type === 'timeZoneName')?.value})`
                                            : ''}
                                        . Within 30 days. Private until
                                        submitted;{' '}
                                        {auth.isAdmin
                                            ? 'your commit will be approved automatically.'
                                            : 'then submitted for review, not published automatically.'}
                                    </p>
                                    <InputError
                                        id="schedule-error"
                                        message={errors.scheduled_at}
                                    />
                                    <Link
                                        href={ScheduledCommitController.index()}
                                        className="text-xs font-medium text-primary hover:underline"
                                    >
                                        Scheduled commits →
                                    </Link>
                                </div>
                            )}

                            <div className="mt-2 flex flex-wrap items-center justify-end gap-x-3 gap-y-2">
                                <div className="mr-auto flex min-w-0 items-center gap-4">
                                    {!isScheduling && (
                                        <ImagePickerButton
                                            count={attachments.images.length}
                                            disabled={processing}
                                            onPick={attachments.attach}
                                            className="-ml-2"
                                        />
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
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    aria-expanded={isScheduling}
                                    aria-controls="commit-schedule"
                                    disabled={
                                        processing ||
                                        (!isScheduling && hasImages)
                                    }
                                    title={
                                        !isScheduling && hasImages
                                            ? "Images can't be scheduled yet"
                                            : undefined
                                    }
                                    onClick={() => {
                                        setTimezone(
                                            Intl.DateTimeFormat().resolvedOptions()
                                                .timeZone,
                                        );
                                        setIsScheduling(!isScheduling);
                                    }}
                                    className="gap-1.5 px-2 text-muted-foreground"
                                >
                                    <Clock3
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Schedule
                                </Button>
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
                                    disabled={processing || !isSubmittable}
                                    title={
                                        isScheduling
                                            ? 'Schedule commit (⌘/Ctrl + Enter)'
                                            : 'Commit (⌘/Ctrl + Enter)'
                                    }
                                    className="min-w-[7.5rem]"
                                    data-test="create-commit-button"
                                >
                                    {isScheduling
                                        ? processing
                                            ? 'Scheduling…'
                                            : 'Schedule commit'
                                        : processing
                                          ? 'Committing…'
                                          : 'Commit'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}
