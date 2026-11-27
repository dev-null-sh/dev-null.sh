import { useHttp, usePage } from '@inertiajs/react';
import { Eye, Link2 } from 'lucide-react';
import type { KeyboardEvent, RefObject } from 'react';
import { useEffect, useEffectEvent, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitPreviewController from '@/actions/App/Http/Controllers/CommitPreviewController';
import { ActionTooltip } from '@/components/action-tooltip';
import { CommitBody } from '@/components/commit-body';
import { objectUrlFor } from '@/components/commit-image-picker';
import { CommitImages } from '@/components/commit-images';
import { CommitPoll } from '@/components/commit-poll';
import { hasMention, typedGuildHandles } from '@/components/linkified-text';
import type { PollDraft } from '@/components/poll-editor';
import { Button } from '@/components/ui/button';
import { useNow } from '@/hooks/use-now';
import { bodySegments, hasCodeBlock } from '@/lib/code';
import { hasModKey, useKeyLabels } from '@/lib/keyboard';
import { cn } from '@/lib/utils';
import type { BodySegment } from '@/types';
import type { GuildMentions } from '@/types/guild';

type Mentions = Record<string, string>;

type Resolved = {
    body: string;
    guildHandle?: string;
    segments: BodySegment[];
    mentions: Mentions;
    organizationMentions: Mentions;
    guildMentions: GuildMentions;
};

const isModifierEnter = (event: KeyboardEvent) =>
    event.key === 'Enter' && hasModKey(event);

const isPreviewShortcut = (event: KeyboardEvent) =>
    event.key.toLowerCase() === 'p' && event.shiftKey && hasModKey(event);

export type ComposerPreview = ReturnType<typeof useComposerPreview>;

/**
 * Switches a composer between writing and previewing its draft as the commit it becomes. The draft is rendered right
 * away unless guild badges need resolving first, so their names never replace hashtags in an already visible body.
 * Code highlighting and mention links come from the server.
 */
export function useComposerPreview({
    body,
    guildHandle,
    textareaRef,
    isSubmittable,
}: {
    body: string;
    guildHandle?: string;
    textareaRef: RefObject<HTMLTextAreaElement | null>;
    isSubmittable: boolean;
}) {
    const { guildsEnabled } = usePage().props;
    const [isPreviewing, setIsPreviewing] = useState(false);
    const [isResolving, setIsResolving] = useState(false);
    const [resolved, setResolved] = useState<Resolved | null>(null);
    const lookup = useRef<AbortController | null>(null);
    const pendingLookup = useRef<AbortController | null>(null);
    const previewId = useId();
    const request = useHttp<
        { body: string; guild_handle?: string },
        {
            segments: BodySegment[];
            mentions: Mentions | [];
            organization_mentions: Mentions | [];
            guild_mentions: GuildMentions | [];
        }
    >({
        body: '',
    });

    const draft = body.trim();
    const isResolved =
        resolved?.body === draft && resolved.guildHandle === guildHandle;
    const segments = isResolved ? resolved.segments : bodySegments(draft);
    const handles = guildsEnabled ? typedGuildHandles(draft) : [];
    const needsPreviewRequest =
        hasCodeBlock(segments) || hasMention(segments) || handles.length > 0;
    const cancelRequest = useEffectEvent(() => request.cancel());

    useEffect(
        () => () => {
            lookup.current?.abort();
            cancelRequest();
            lookup.current = null;
            pendingLookup.current = null;
            setIsResolving(false);
        },
        [draft, guildHandle],
    );

    const resolve = async (controller: AbortController) => {
        if (isResolved || !needsPreviewRequest) {
            return;
        }

        request.transform(() => ({
            body: body.trim(),
            ...(guildHandle && { guild_handle: guildHandle }),
        }));
        let validationRejected = false;

        try {
            const response = await request.submit(
                CommitPreviewController.store(),
                {
                    onError: () => {
                        validationRejected = true;
                    },
                },
            );

            if (controller.signal.aborted) {
                return;
            }

            setResolved({
                body: draft,
                guildHandle,
                segments: response.segments,
                mentions: Array.isArray(response.mentions)
                    ? {}
                    : response.mentions,
                organizationMentions: Array.isArray(
                    response.organization_mentions,
                )
                    ? {}
                    : response.organization_mentions,
                guildMentions: Array.isArray(response.guild_mentions)
                    ? {}
                    : response.guild_mentions,
            });
        } catch {
            if (controller.signal.aborted) {
                return;
            }

            if (validationRejected) {
                setResolved({
                    body: draft,
                    guildHandle,
                    segments: bodySegments(draft),
                    mentions: {},
                    organizationMentions: {},
                    guildMentions: {},
                });

                return;
            }

            // Keep writing when guild names could not be resolved, rather than changing the visible preview later.
            if (handles.length > 0) {
                toast.error('Could not load the preview. Try again.');
                controller.abort();
            }
        }
    };

    const show = async (previewing: boolean, moveFocus: boolean) => {
        if (previewing && pendingLookup.current) {
            return;
        }

        lookup.current?.abort();
        request.cancel();
        const controller = new AbortController();
        lookup.current = controller;
        pendingLookup.current = null;
        setIsResolving(false);

        if (previewing) {
            const resolving = resolve(controller);

            if (handles.length > 0 && !isResolved) {
                pendingLookup.current = controller;
                setIsResolving(true);
                await resolving;
                if (lookup.current === controller) {
                    pendingLookup.current = null;
                    setIsResolving(false);
                }
            }
        }

        if (controller.signal.aborted) {
            if (moveFocus && lookup.current === controller) {
                textareaRef.current?.focus();
            }

            return;
        }

        setIsPreviewing(previewing);

        if (moveFocus) {
            requestAnimationFrame(() => {
                if (controller.signal.aborted) {
                    return;
                }

                (previewing
                    ? document.getElementById(previewId)
                    : textareaRef.current
                )?.focus();
            });
        }
    };

    return {
        isPreviewing,
        isResolving,
        draft,
        segments,
        mentions: isResolved ? resolved.mentions : null,
        organizationMentions: isResolved ? resolved.organizationMentions : null,
        guildMentions: isResolved ? resolved.guildMentions : undefined,
        previewId,
        toggle: (moveFocus = false) => show(!isPreviewing, moveFocus),
        close: () => {
            void show(false, false);
        },
        /**
         * Handles ⌘/Ctrl + Shift + P in the textarea, and tells whether it did.
         */
        handleShortcut: (event: KeyboardEvent<HTMLTextAreaElement>) => {
            if (!isPreviewShortcut(event)) {
                return false;
            }

            event.preventDefault();
            void show(true, true);

            return true;
        },
        onPreviewKeyDown: (event: KeyboardEvent<HTMLDivElement>) => {
            if (isPreviewShortcut(event) || event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                void show(false, true);
            }

            if (isModifierEnter(event) && isSubmittable) {
                event.preventDefault();
                event.currentTarget.closest('form')?.requestSubmit();
            }
        },
    };
}

export function PreviewToggle({
    preview,
    className,
}: {
    preview: ComposerPreview;
    className?: string;
}) {
    const keys = useKeyLabels();

    return (
        <ActionTooltip
            label={preview.isPreviewing ? 'Edit' : 'Preview'}
            shortcut={keys.combo(keys.mod, keys.shift, 'P')}
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={() => preview.toggle(true)}
                aria-pressed={preview.isPreviewing}
                aria-busy={preview.isResolving}
                aria-disabled={preview.isResolving}
                aria-label="Preview"
                aria-keyshortcuts="Meta+Shift+P Control+Shift+P"
                className={cn(
                    'text-muted-foreground aria-pressed:bg-primary-soft aria-pressed:text-primary aria-pressed:hover:bg-primary-soft aria-pressed:hover:text-primary',
                    className,
                )}
                data-test="preview-toggle"
            >
                <Eye className="size-5" />
            </Button>
        </ActionTooltip>
    );
}

export function ComposerPreviewBody({
    preview,
    images,
    descriptions = [],
    url,
    className,
}: {
    preview: ComposerPreview;
    images: File[];
    descriptions?: string[];
    url: string | null;
    className?: string;
}) {
    return (
        <div
            id={preview.previewId}
            tabIndex={-1}
            role="region"
            aria-label="Preview"
            onKeyDown={preview.onPreviewKeyDown}
            className={cn('min-w-0 outline-none', className)}
            data-composer-preview
            data-test="composer-preview"
        >
            {preview.segments.length > 0 ? (
                <CommitBody
                    segments={preview.segments}
                    mentions={preview.mentions}
                    organizationMentions={preview.organizationMentions}
                    guildMentions={preview.guildMentions}
                    className="text-[15px]"
                />
            ) : (
                <p className="text-[15px] leading-relaxed text-muted-foreground">
                    Nothing to preview yet.
                </p>
            )}

            <CommitImages
                images={images.map((image, index) => ({
                    id: objectUrlFor(image),
                    url: objectUrlFor(image),
                    description: descriptions[index]?.trim() || null,
                }))}
            />

            {url && (
                <div
                    className="mt-3 flex items-center gap-3 rounded-lg border border-dashed px-3 py-2"
                    data-test="link-card-preview"
                >
                    <Link2
                        aria-hidden
                        className="size-4 shrink-0 text-muted-foreground"
                    />
                    <div className="min-w-0">
                        <p className="truncate font-mono text-[13px]">
                            {url.replace(/^https?:\/\//i, '')}
                        </p>
                        <p className="text-sm leading-snug text-muted-foreground">
                            Unfurls into a link card after you commit.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Shows a poll draft as it reads once committed: open right away for admins, whose commits skip moderation, and
 * waiting for approval for everyone else.
 */
export function ComposerPollPreview({
    poll,
    className,
}: {
    poll: PollDraft;
    className?: string;
}) {
    const { auth } = usePage().props;
    const now = useNow();

    return (
        <div className={className} data-test="composer-poll-preview">
            <CommitPoll
                commit={{ id: '', author: { id: '' }, status: 'pending' }}
                poll={{
                    id: '',
                    closes_at: auth.isAdmin
                        ? new Date(
                              now.getTime() + poll.duration * 60_000,
                          ).toISOString()
                        : null,
                    is_closed: false,
                    has_voted: false,
                    total_votes: null,
                    options: poll.options
                        .map((option) => option.trim())
                        .filter((option) => option !== '')
                        .map((label, index) => ({
                            id: String(index),
                            label,
                            votes_count: null,
                            voted: false,
                        })),
                }}
                isStatic
            />
        </div>
    );
}
