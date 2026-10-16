import { Form, Link, usePage } from '@inertiajs/react';
import { ChartBar, Clock3, Plus, X } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { createRef, useMemo, useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitSeriesController from '@/actions/App/Http/Controllers/CommitSeriesController';
import ScheduledCommitController from '@/actions/App/Http/Controllers/ScheduledCommitController';
import {
    ImagePickerButton,
    ImagePickerPreviews,
    imageErrorFrom,
} from '@/components/commit-image-picker';
import { EmojiSuggestionHint } from '@/components/emoji-suggestion';
import { GraphRail, RailAvatar } from '@/components/graph-rail';
import InputError from '@/components/input-error';
import { LinkCardPicker } from '@/components/link-card-picker';
import { MentionTextarea } from '@/components/mention-textarea';
import type { PollDraft } from '@/components/poll-editor';
import { emptyPollDraft, PollEditor } from '@/components/poll-editor';
import { SecretWarning } from '@/components/secret-warning';
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { useLinkCards } from '@/hooks/use-link-card';
import { useSecretWarning } from '@/hooks/use-secret-warning';
import { moveCaretToEnd, useSeriesStash } from '@/hooks/use-stash';
import { useUser } from '@/hooks/use-user';
import { completeEmoji, suggestEmoji } from '@/lib/emoji';
import { characterCount, cn, laneColor } from '@/lib/utils';

const minBodyLength = 3;
const maxBodyLength = 280;
const maxParts = 10;

const partId = (index: number): string =>
    index === 0 ? 'body' : `body-${index + 1}`;

function focusPart(index: number, atEnd = false): void {
    requestAnimationFrame(() => {
        const part = document.getElementById(partId(index));

        if (!(part instanceof HTMLTextAreaElement)) {
            return;
        }

        part.focus();

        if (atEnd) {
            part.setSelectionRange(part.value.length, part.value.length);
        }
    });
}

function RemainingCount({ id, body }: { id: string; body: string }) {
    const remaining = maxBodyLength - characterCount(body);

    return (
        <span
            id={id}
            aria-label={`${remaining} characters left`}
            className={cn('font-mono text-[13px] text-muted-foreground', {
                'text-amber-700 dark:text-amber-400': remaining <= 20,
                'text-destructive': remaining === 0,
            })}
        >
            {remaining}
        </span>
    );
}

export function CommitComposer() {
    const [isScheduling, setIsScheduling] = useState(false);
    const [scheduledAt, setScheduledAt] = useState('');
    const [timezone, setTimezone] = useState('');
    const { auth } = usePage().props;
    const { user } = useUser();
    const { bodies, isRestored, updateBody, insertPart, removePart, drop } =
        useSeriesStash(user.id, 'commit');
    const attachments = useImageAttachments();
    const [poll, setPoll] = useState<PollDraft | null>(null);
    const hasImages = attachments.images.length > 0;
    const linkCards = useLinkCards(bodies);
    const textareas = useMemo(
        () =>
            Array.from({ length: maxParts }, () =>
                createRef<HTMLTextAreaElement>(),
            ),
        [],
    );
    const secrets = useSecretWarning(bodies);
    const firstSecretPart = secrets.findingsByPart.findIndex(
        (findings) => findings.length > 0,
    );

    const isSeries = bodies.length > 1;
    const canAddPart =
        !isScheduling && bodies.length < maxParts && poll === null;
    const isCommittable =
        bodies.every((body) => characterCount(body.trim()) >= minBodyLength) &&
        (poll === null ||
            poll.options.every((option) => option.trim().length > 0));

    const isSchedulingImages = isScheduling && hasImages;
    const isSubmittable =
        isCommittable &&
        !isSchedulingImages &&
        (!isScheduling || poll === null);

    const addPartAfter = (index: number) => {
        if (!canAddPart) {
            return;
        }

        insertPart(index + 1);
        linkCards.insertPart(index + 1);
        focusPart(index + 1);
    };

    const removePartAt = (index: number) => {
        removePart(index);
        linkCards.removePart(index);
        focusPart(Math.max(index - 1, 0), true);
    };

    const onKeyDown = (
        index: number,
        event: KeyboardEvent<HTMLTextAreaElement>,
    ) => {
        if (event.key === 'Tab' && suggestEmoji(bodies[index]) !== null) {
            const completed = completeEmoji(bodies[index]);

            if (completed !== null) {
                event.preventDefault();
                updateBody(index, completed);
            }

            return;
        }

        const isModEnter =
            event.key === 'Enter' && (event.metaKey || event.ctrlKey);

        if (isModEnter && event.shiftKey) {
            event.preventDefault();
            addPartAfter(index);

            return;
        }

        if (isModEnter && isSubmittable) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();

            return;
        }

        if (event.key === 'Backspace' && isSeries && bodies[index] === '') {
            event.preventDefault();
            removePartAt(index);
        }
    };

    return (
        <Form
            {...(isScheduling
                ? ScheduledCommitController.store.form()
                : isSeries
                  ? CommitSeriesController.store.form()
                  : CommitController.store.form())}
            onBefore={secrets.beforeSubmit}
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
                if (!page.flash.committed) {
                    return;
                }

                drop();
                setScheduledAt('');
                setIsScheduling(false);
                attachments.clear();
                setPoll(null);
                linkCards.reset();
                focusPart(0);
            }}
            className="flex flex-col"
            data-test="commit-composer"
        >
            {({ processing, errors }) => (
                <>
                    {bodies.map((body, index) => {
                        const id = partId(index);
                        const isLast = index === bodies.length - 1;
                        const isFilled =
                            characterCount(body.trim()) >= minBodyLength;
                        const suggestion = suggestEmoji(body);
                        const error =
                            errors[`bodies.${index}`] ??
                            (index === 0 ? errors.body : undefined);

                        return (
                            <div
                                key={index}
                                className="flex"
                                data-test="commit-composer-part"
                            >
                                <GraphRail
                                    lane={laneColor(user.lane)}
                                    previousLane={
                                        index > 0 ? 'var(--rail)' : undefined
                                    }
                                    variant="staged"
                                    isFilled={isFilled}
                                    node={
                                        index === 0 ? (
                                            <RailAvatar
                                                user={user}
                                                isStaged={!isFilled}
                                            />
                                        ) : undefined
                                    }
                                />

                                <div
                                    className={cn(
                                        'flex min-w-0 flex-1 pt-3 pr-4',
                                        { 'border-b pb-3': isLast },
                                    )}
                                >
                                    <div className="min-w-0 flex-1">
                                        <label htmlFor={id} className="sr-only">
                                            {isSeries
                                                ? `Commit ${index + 1} of ${bodies.length}`
                                                : 'Body'}
                                        </label>
                                        <MentionTextarea
                                            ref={textareas[index]}
                                            id={id}
                                            name={
                                                isSeries ? 'bodies[]' : 'body'
                                            }
                                            rows={1}
                                            value={body}
                                            onValueChange={(text) =>
                                                updateBody(index, text)
                                            }
                                            onPaste={
                                                index === 0 && poll === null
                                                    ? attachments.onPaste
                                                    : undefined
                                            }
                                            onDragOver={
                                                index === 0 && poll === null
                                                    ? attachments.onDragOver
                                                    : undefined
                                            }
                                            onDrop={
                                                index === 0 && poll === null
                                                    ? attachments.onDrop
                                                    : undefined
                                            }
                                            onKeyDown={(event) =>
                                                onKeyDown(index, event)
                                            }
                                            onFocus={
                                                isRestored
                                                    ? moveCaretToEnd
                                                    : undefined
                                            }
                                            maxLength={maxBodyLength}
                                            placeholder={
                                                poll
                                                    ? 'Ask a question'
                                                    : index === 0
                                                      ? 'What did you ship?'
                                                      : 'And then?'
                                            }
                                            autoComplete="off"
                                            aria-invalid={
                                                error ? true : undefined
                                            }
                                            aria-describedby={`${id}-count`}
                                            className="block field-sizing-content min-h-10 w-full resize-none overflow-x-hidden bg-transparent py-1.5 text-[17px] leading-relaxed outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                                        />

                                        <EmojiSuggestionHint
                                            suggestion={suggestion}
                                            className="mt-1.5"
                                        />

                                        <InputError
                                            message={
                                                error ??
                                                errors[
                                                    `link_preview_urls.${index}`
                                                ] ??
                                                (index === 0
                                                    ? errors.link_preview_url
                                                    : undefined)
                                            }
                                        />
                                        <SecretWarning
                                            body={body}
                                            findings={
                                                secrets.findingsByPart[index]
                                            }
                                            textarea={textareas[index]}
                                            onChange={(text) =>
                                                updateBody(index, text)
                                            }
                                            confirming={
                                                secrets.confirming &&
                                                index === firstSecretPart
                                            }
                                            onConfirmingChange={
                                                secrets.setConfirming
                                            }
                                            onApprove={secrets.approve}
                                        />

                                        {index === 0 && (
                                            <>
                                                <ImagePickerPreviews
                                                    images={attachments.images}
                                                    onRemove={
                                                        attachments.remove
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        attachments.error ??
                                                        (isSchedulingImages
                                                            ? "Images can't be scheduled yet. Remove them to schedule this commit."
                                                            : imageErrorFrom(
                                                                  errors,
                                                              ))
                                                    }
                                                    className="mt-1"
                                                />
                                            </>
                                        )}

                                        {poll && index === 0 && (
                                            <PollEditor
                                                poll={poll}
                                                errors={errors}
                                                onChange={setPoll}
                                                onRemove={() => {
                                                    setPoll(null);
                                                    focusPart(0);
                                                }}
                                            />
                                        )}

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
                                                        const value =
                                                            event.target.value;
                                                        setScheduledAt(value);
                                                        const date = new Date(
                                                            value,
                                                        );
                                                        const pad = (
                                                            n: number,
                                                        ) =>
                                                            String(n).padStart(
                                                                2,
                                                                '0',
                                                            );
                                                        const local = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
                                                        event.target.setCustomValidity(
                                                            value &&
                                                                local !== value
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
                                                        new Date(
                                                            scheduledAt,
                                                        ).getTime(),
                                                    )
                                                        ? ` (${new Intl.DateTimeFormat(undefined, { timeZoneName: 'shortOffset' }).formatToParts(new Date(scheduledAt)).find((part) => part.type === 'timeZoneName')?.value})`
                                                        : ''}
                                                    . Within 30 days. Private
                                                    until submitted;{' '}
                                                    {auth.isAdmin
                                                        ? 'your commit will be approved automatically.'
                                                        : 'then submitted for review, not published automatically.'}
                                                </p>
                                                <InputError
                                                    id="schedule-error"
                                                    message={
                                                        errors.scheduled_at
                                                    }
                                                />
                                                <Link
                                                    href={ScheduledCommitController.index()}
                                                    className="text-xs font-medium text-primary hover:underline"
                                                >
                                                    Scheduled commits →
                                                </Link>
                                            </div>
                                        )}

                                        {isSeries && (
                                            <div className="mt-1 flex items-center gap-3">
                                                <span
                                                    className="font-mono text-[13px] text-muted-foreground"
                                                    data-test="commit-composer-position"
                                                >
                                                    {index + 1}/{bodies.length}
                                                </span>
                                                <LinkCardPicker
                                                    linkCard={
                                                        linkCards.cards[index]
                                                    }
                                                    name={`link_preview_urls[${index}]`}
                                                    disabled={processing}
                                                />
                                                <span className="ml-auto">
                                                    <RemainingCount
                                                        id={`${id}-count`}
                                                        body={body}
                                                    />
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removePartAt(index)
                                                    }
                                                    aria-label={`Remove commit ${index + 1}`}
                                                    title="Remove this commit"
                                                    className="-mr-1.5 rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                                    data-test="remove-commit-part-button"
                                                >
                                                    <X className="size-4" />
                                                </button>
                                            </div>
                                        )}

                                        {isLast && (
                                            <>
                                                <InputError
                                                    message={errors.bodies}
                                                />

                                                <div className="mt-2 flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                                                    <div className="mr-auto flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                                                        {!isScheduling && (
                                                            <ImagePickerButton
                                                                count={
                                                                    attachments
                                                                        .images
                                                                        .length
                                                                }
                                                                disabled={
                                                                    processing ||
                                                                    poll !==
                                                                        null
                                                                }
                                                                onPick={
                                                                    attachments.attach
                                                                }
                                                                className="-ml-2"
                                                            />
                                                        )}
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            disabled={
                                                                processing ||
                                                                poll !== null ||
                                                                hasImages ||
                                                                isSeries ||
                                                                isScheduling
                                                            }
                                                            onClick={() => {
                                                                setPoll(
                                                                    emptyPollDraft,
                                                                );
                                                                requestAnimationFrame(
                                                                    () =>
                                                                        document
                                                                            .getElementById(
                                                                                'poll-option-0',
                                                                            )
                                                                            ?.focus(),
                                                                );
                                                            }}
                                                            aria-label="Add poll"
                                                            title={
                                                                isScheduling
                                                                    ? 'Polls cannot be scheduled yet'
                                                                    : hasImages
                                                                      ? 'A commit can have images or a poll, not both'
                                                                      : isSeries
                                                                        ? 'Polls are available on single commits'
                                                                        : 'Add poll'
                                                            }
                                                            className="text-muted-foreground"
                                                            data-test="add-poll-button"
                                                        >
                                                            <ChartBar className="size-5" />
                                                        </Button>
                                                        {!isScheduling &&
                                                            !isSeries && (
                                                                <LinkCardPicker
                                                                    linkCard={
                                                                        linkCards
                                                                            .cards[0]
                                                                    }
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                    className="-ml-3"
                                                                />
                                                            )}
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                addPartAfter(
                                                                    index,
                                                                )
                                                            }
                                                            disabled={
                                                                !canAddPart
                                                            }
                                                            title={
                                                                isScheduling
                                                                    ? 'Series cannot be scheduled yet'
                                                                    : poll !==
                                                                        null
                                                                      ? 'Remove the poll to start a series'
                                                                      : canAddPart
                                                                        ? 'Add another commit (⌘/Ctrl + Shift + Enter)'
                                                                        : `A series has at most ${maxParts} commits`
                                                            }
                                                            className="flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                                                            data-test="add-commit-part-button"
                                                        >
                                                            <Plus className="size-4" />
                                                            Add commit
                                                        </button>
                                                        {isRestored && (
                                                            <StashLabel
                                                                onDrop={() => {
                                                                    drop();
                                                                    linkCards.reset();
                                                                    focusPart(
                                                                        0,
                                                                    );
                                                                }}
                                                                className="text-[13px]"
                                                            />
                                                        )}
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-expanded={
                                                            isScheduling
                                                        }
                                                        aria-controls="commit-schedule"
                                                        disabled={
                                                            processing ||
                                                            (!isScheduling &&
                                                                (hasImages ||
                                                                    isSeries ||
                                                                    poll !==
                                                                        null))
                                                        }
                                                        title={
                                                            !isScheduling &&
                                                            poll !== null
                                                                ? 'Polls cannot be scheduled yet'
                                                                : !isScheduling &&
                                                                    isSeries
                                                                  ? 'Series cannot be scheduled yet'
                                                                  : !isScheduling &&
                                                                      hasImages
                                                                    ? "Images can't be scheduled yet"
                                                                    : undefined
                                                        }
                                                        onClick={() => {
                                                            setTimezone(
                                                                Intl.DateTimeFormat().resolvedOptions()
                                                                    .timeZone,
                                                            );
                                                            setIsScheduling(
                                                                !isScheduling,
                                                            );
                                                        }}
                                                        className="gap-1.5 px-2 text-muted-foreground"
                                                    >
                                                        <Clock3
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        Schedule
                                                    </Button>
                                                    {!isSeries && (
                                                        <RemainingCount
                                                            id="body-count"
                                                            body={body}
                                                        />
                                                    )}
                                                    <Button
                                                        type="submit"
                                                        disabled={
                                                            processing ||
                                                            !isSubmittable
                                                        }
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
                                                              : isSeries
                                                                ? `Commit ${bodies.length}`
                                                                : 'Commit'}
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </>
            )}
        </Form>
    );
}
