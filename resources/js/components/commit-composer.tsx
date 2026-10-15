import { Form, usePage } from '@inertiajs/react';
import type { KeyboardEvent } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import {
    ImagePickerButton,
    ImagePickerPreviews,
    imageErrorFrom,
} from '@/components/commit-image-picker';
import { GraphRail } from '@/components/graph-rail';
import InputError from '@/components/input-error';
import { LinkCardPicker } from '@/components/link-card-picker';
import { MentionTextarea } from '@/components/mention-textarea';
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { useLinkCard } from '@/hooks/use-link-card';
import { moveCaretToEnd, useStash } from '@/hooks/use-stash';
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
    const linkCard = useLinkCard(body);

    const remaining = maxBodyLength - body.length;
    const isCommittable = body.trim().length >= minBodyLength;

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
                linkCard.reset();
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
                                onKeyDown={submitOnModEnter}
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
                                className="block field-sizing-content min-h-10 w-full resize-none bg-transparent py-1.5 text-[17px] leading-relaxed outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                            />

                            <InputError
                                message={errors.body ?? errors.link_preview_url}
                            />

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

                            <div className="mt-2 flex items-center justify-end gap-4">
                                <div className="mr-auto flex min-w-0 items-center gap-4">
                                    <ImagePickerButton
                                        count={attachments.images.length}
                                        disabled={processing}
                                        onPick={attachments.attach}
                                        className="-ml-2"
                                    />
                                    <LinkCardPicker
                                        linkCard={linkCard}
                                        disabled={processing}
                                        className="-ml-3"
                                    />
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
