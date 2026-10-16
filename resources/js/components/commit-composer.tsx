import { Form } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitSeriesController from '@/actions/App/Http/Controllers/CommitSeriesController';
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
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { useLinkCards } from '@/hooks/use-link-card';
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
    const { user } = useUser();
    const { bodies, isRestored, updateBody, insertPart, removePart, drop } =
        useSeriesStash(user.id, 'commit');
    const attachments = useImageAttachments();
    const linkCards = useLinkCards(bodies);

    const isSeries = bodies.length > 1;
    const canAddPart = bodies.length < maxParts;
    const isCommittable = bodies.every(
        (body) => characterCount(body.trim()) >= minBodyLength,
    );

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

        if (isModEnter && isCommittable) {
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
            {...(isSeries
                ? CommitSeriesController.store.form()
                : CommitController.store.form())}
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
                                                index === 0
                                                    ? attachments.onPaste
                                                    : undefined
                                            }
                                            onDragOver={
                                                index === 0
                                                    ? attachments.onDragOver
                                                    : undefined
                                            }
                                            onDrop={
                                                index === 0
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
                                                index === 0
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
                                                        imageErrorFrom(errors)
                                                    }
                                                    className="mt-1"
                                                />
                                            </>
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
                                                    <div className="mr-auto flex min-w-0 items-center gap-3">
                                                        <ImagePickerButton
                                                            count={
                                                                attachments
                                                                    .images
                                                                    .length
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                            onPick={
                                                                attachments.attach
                                                            }
                                                            className="-ml-2"
                                                        />
                                                        {!isSeries && (
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
                                                                canAddPart
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
                                                            !isCommittable
                                                        }
                                                        title="Commit (⌘/Ctrl + Enter)"
                                                        className="min-w-[7.5rem]"
                                                        data-test="create-commit-button"
                                                    >
                                                        {processing
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
