import { Image, Reply, SmilePlus } from 'lucide-react';
import type { PointerEvent, ReactNode, RefObject } from 'react';
import { useRef, useState } from 'react';
import { show as showMessageImage } from '@/actions/App/Http/Controllers/MessageImageController';
import MessageLinkPreviewController from '@/actions/App/Http/Controllers/MessageLinkPreviewController';
import { preloadEmojiGroups } from '@/components/chat/message-reactions';
import { CommitBody } from '@/components/commit-body';
import { objectUrlFor } from '@/components/commit-image-picker';
import { CommitImages } from '@/components/commit-images';
import type { ShownImage } from '@/components/commit-images';
import { ImageViewer } from '@/components/image-viewer';
import { LinkPreview } from '@/components/link-preview';
import { bodySegments } from '@/lib/code';
import { cn, laneColor } from '@/lib/utils';
import type {
    BodySegment,
    Lane,
    Message,
    MessageImage,
    MessageReply,
} from '@/types';

/**
 * Who wrote a message, as a reply's ghost names them.
 */
export type Author = { name: string; lane: Lane; isMe: boolean };

/**
 * Links, mentions and code keep the primary bubble's colours, so they stay readable on it.
 */
const onPrimary =
    '[&_a]:text-primary-foreground [&_a]:underline [&_a]:decoration-primary-foreground/50 [&_a]:underline-offset-2 [&_.text-muted-foreground]:text-primary-foreground/70 [&_.fill-foreground]:fill-primary-foreground [&_code]:bg-primary-foreground/15';

export function MessageText({
    message,
    isMine,
}: {
    message: Pick<Message, 'body' | 'body_segments' | 'mentions'>;
    isMine: boolean;
}) {
    const segments: BodySegment[] =
        message.body_segments ?? bodySegments(message.body);

    return (
        <CommitBody
            segments={segments}
            mentions={message.mentions ?? null}
            className={cn('leading-snug', { [onPrimary]: isMine })}
        />
    );
}

const isUnsent = (images: MessageImage[] | File[]): images is File[] =>
    images[0] instanceof File;

const downloadUrl = (image: ShownImage): string =>
    showMessageImage.url(image.id, { query: { download: 1 } });

export function MessageImages({ images }: { images: MessageImage[] | File[] }) {
    const [viewedIndex, setViewedIndex] = useState<number | null>(null);

    if (images.length === 0) {
        return null;
    }

    return (
        <div className="w-64 max-w-full sm:w-72 [&_img]:max-h-80 [&>div]:mt-0">
            {isUnsent(images) ? (
                <CommitImages
                    images={images.map((image) => ({
                        id: objectUrlFor(image),
                        url: objectUrlFor(image),
                        description: null,
                    }))}
                />
            ) : (
                <>
                    <CommitImages images={images} onOpen={setViewedIndex} />
                    <ImageViewer
                        images={images}
                        index={viewedIndex}
                        onView={setViewedIndex}
                        onClose={() => setViewedIndex(null)}
                        downloadUrl={downloadUrl}
                    />
                </>
            )}
        </div>
    );
}

export function MessageLinkCard({ message }: { message: Message }) {
    if (!message.link_preview) {
        return null;
    }

    return (
        <div className="w-64 max-w-full *:mt-1 sm:w-80">
            <LinkPreview
                refreshUrl={MessageLinkPreviewController.show.url(message.id)}
                preview={message.link_preview}
            />
        </div>
    );
}

function snippetOf(reply: MessageReply): ReactNode {
    const body = reply.body
        .replace(/```[\s\S]*?(```|$)/g, ' code ')
        .replace(/`/g, '')
        .replace(/https?:\/\//gi, '')
        .replace(/\s+/g, ' ')
        .trim();

    if (body !== '') {
        return body;
    }

    return (
        <span className="inline-flex items-center gap-1">
            <Image aria-hidden className="size-3.5" />
            {reply.images_count > 1 ? `${reply.images_count} images` : 'Image'}
        </span>
    );
}

/**
 * The message a reply answers, peeking out from behind it: who wrote it, in their lane colour, over its first line.
 * In the composer it sits flat, above the field.
 */
export function ReplyGhost({
    reply,
    author,
    side,
    isDraft = false,
    onJump,
}: {
    reply: MessageReply;
    author: Author;
    side: 'mine' | 'theirs';
    isDraft?: boolean;
    onJump?: (id: number) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onJump?.(reply.id)}
            disabled={onJump === undefined}
            aria-label={`In reply to ${author.name}: ${reply.body || 'an image'}`}
            className={cn(
                'flex max-w-full min-w-0 flex-col items-start rounded-lg px-3 text-left text-[13px] leading-snug transition-colors enabled:cursor-pointer',
                author.isMe
                    ? 'bg-primary/20 text-foreground/75'
                    : 'bg-muted/80 text-muted-foreground',
                isDraft
                    ? 'py-1.5'
                    : cn(
                          '-mb-3.5 scale-[.97] pt-1.5 pb-5 enabled:hover:bg-muted',
                          side === 'mine'
                              ? 'origin-bottom-right'
                              : 'origin-bottom-left',
                      ),
            )}
            data-test="reply-ghost"
        >
            <span
                className="max-w-full truncate text-xs font-semibold"
                style={{ color: laneColor(author.lane) }}
            >
                {author.name}
            </span>
            <span className="max-w-full truncate">{snippetOf(reply)}</span>
        </button>
    );
}

/**
 * Lets touch users swipe a message right to reply, and shows its reply button on hover or focus.
 */
export function Repliable({
    isMine,
    onReply,
    children,
}: {
    isMine: boolean;
    onReply: (() => void) | null;
    children: ReactNode;
}) {
    const [offset, setOffset] = useState(0);
    const start = useRef<{ x: number; y: number; id: number } | null>(null);

    if (onReply === null) {
        return children;
    }

    // The image viewer is portalled out of the message, but React still bubbles its swipes up to here.
    const onPointerDown = (event: PointerEvent<HTMLElement>) => {
        if (
            event.pointerType === 'touch' &&
            event.currentTarget.contains(event.target as Node)
        ) {
            start.current = {
                x: event.clientX,
                y: event.clientY,
                id: event.pointerId,
            };
        }
    };

    const onPointerMove = (event: PointerEvent) => {
        const swipe = start.current;

        if (swipe === null || swipe.id !== event.pointerId) {
            return;
        }

        if (offset === 0 && Math.abs(event.clientY - swipe.y) > 16) {
            start.current = null;

            return;
        }

        setOffset(Math.max(0, Math.min(72, event.clientX - swipe.x)));
    };

    const end = (isReplying: boolean) => {
        if (isReplying && offset >= 56) {
            onReply();
        }

        start.current = null;
        setOffset(0);
    };

    return (
        <div
            className={cn('group/message flex w-full touch-pan-y', {
                'justify-end': isMine,
                'transition-transform duration-200': offset === 0,
            })}
            style={
                offset > 0
                    ? { transform: `translateX(${offset}px)` }
                    : undefined
            }
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={() => end(true)}
            onPointerCancel={() => end(false)}
        >
            {children}
        </div>
    );
}

/**
 * Reply and react, just outside the end of the bubble they belong to and vertically centred on it, so they take no
 * room in the thread. They show on hover or focus, and stay while the reaction tray under the message is open.
 */
export function BubbleActions({
    isMine,
    onReply,
    onReact,
    isReacting,
    reactRef,
    trayId,
}: {
    isMine: boolean;
    onReply: (() => void) | null;
    onReact: (() => void) | null;
    isReacting: boolean;
    reactRef: RefObject<HTMLButtonElement | null>;
    trayId: string;
}) {
    if (onReply === null && onReact === null) {
        return null;
    }

    const button =
        'grid size-7 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

    return (
        <div
            data-open={isReacting ? '' : undefined}
            className={cn(
                'absolute top-1/2 flex -translate-y-1/2 items-center gap-0.5 opacity-0 transition-opacity group-hover/message:opacity-100 has-focus-visible:opacity-100 data-open:opacity-100 pointer-coarse:hidden',
                isMine
                    ? 'right-full mr-1.5 flex-row-reverse'
                    : 'left-full ml-1.5',
            )}
            data-test="bubble-actions"
        >
            {onReply !== null && (
                <button
                    type="button"
                    onClick={onReply}
                    aria-label="Reply"
                    title="Reply"
                    className={button}
                    data-test="reply-button"
                >
                    <Reply aria-hidden className="size-4" />
                </button>
            )}
            {onReact !== null && (
                <button
                    ref={reactRef}
                    type="button"
                    onClick={onReact}
                    onPointerEnter={() => void preloadEmojiGroups()}
                    aria-label="React"
                    title="React"
                    aria-expanded={isReacting}
                    aria-controls={trayId}
                    className={cn(button, {
                        'bg-accent text-foreground': isReacting,
                    })}
                    data-test="react-button"
                >
                    <SmilePlus aria-hidden className="size-4" />
                </button>
            )}
        </div>
    );
}
