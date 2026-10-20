import { router } from '@inertiajs/react';
import { ImageUp, Move, Trash2 } from 'lucide-react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import {
    destroy,
    update,
} from '@/actions/App/Http/Controllers/UserCoverImageController';
import { CoverImage, CoverPill, coverAspect } from '@/components/cover-image';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useUser } from '@/hooks/use-user';
import {
    acceptedImageTypes,
    PickedImageError,
    readPickedImage,
} from '@/lib/read-picked-images';
import { cn } from '@/lib/utils';

type Offset = { x: number; y: number };

type Draft = {
    file: File;
    src: string;
    width: number;
    height: number;
};

const centered: Offset = { x: 0.5, y: 0.5 };

const maxUploadBytes = 5 * 1024 * 1024;

const invalidCoverMessage = 'Only JPEG, PNG, or WebP images up to 5 MB.';

const clamp = (value: number) =>
    Math.min(1, Math.max(0, Math.round(value * 1000) / 1000));

export function CoverImageSetting({
    url,
    isPending,
}: {
    url: string | null;
    isPending: boolean;
}) {
    const { user } = useUser();
    const input = useRef<HTMLInputElement>(null);
    const drag = useRef<{
        pointer: Offset;
        offset: Offset;
        room: Offset;
    } | null>(null);
    const [draft, setDraft] = useState<Draft | null>(null);
    const [offset, setOffset] = useState<Offset>(centered);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    useEffect(
        () => () => {
            if (draft) {
                URL.revokeObjectURL(draft.src);
            }
        },
        [draft],
    );

    const pick = async (picked: File) => {
        let file: File;

        try {
            file = await readPickedImage(
                picked,
                maxUploadBytes,
                invalidCoverMessage,
            );
        } catch (error) {
            setError(
                error instanceof PickedImageError
                    ? error.message
                    : invalidCoverMessage,
            );

            return;
        }

        const src = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            setError('');
            setOffset(centered);
            setDraft({
                file,
                src,
                width: image.naturalWidth,
                height: image.naturalHeight,
            });
        };
        image.onerror = () => {
            URL.revokeObjectURL(src);
            setError(invalidCoverMessage);
        };
        image.src = src;
    };

    const options = {
        preserveScroll: true,
        only: ['coverImageUrl', 'coverImagePending'],
        onStart: () => {
            setProcessing(true);
            setError('');
        },
        onFinish: () => setProcessing(false),
        onSuccess: () => {
            setDraft(null);
            router.flushAll();
        },
        onError: (errors: Record<string, string>) =>
            setError(
                errors.cover_image ??
                    'Could not save the cover image. Try again.',
            ),
    };

    const save = () =>
        draft &&
        router.put(
            update.url(),
            { cover_image: draft.file, offset_x: offset.x, offset_y: offset.y },
            { ...options, forceFormData: true },
        );

    /**
     * The frame keeps its size during a drag, so how far the image overflows it, and can move, is measured once.
     */
    const startDrag = (event: PointerEvent<HTMLDivElement>) => {
        if (!draft || processing) {
            return;
        }

        const { width, height } = event.currentTarget.getBoundingClientRect();
        const scale = Math.max(width / draft.width, height / draft.height);

        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = {
            pointer: { x: event.clientX, y: event.clientY },
            offset,
            room: {
                x: Math.max(draft.width * scale - width, 1),
                y: Math.max(draft.height * scale - height, 1),
            },
        };
    };

    // Dragging the image down shows more of its top, so the offset moves the other way.
    const moveDrag = (event: PointerEvent<HTMLDivElement>) => {
        const start = drag.current;

        if (!start) {
            return;
        }

        setOffset({
            x: clamp(
                start.offset.x -
                    (event.clientX - start.pointer.x) / start.room.x,
            ),
            y: clamp(
                start.offset.y -
                    (event.clientY - start.pointer.y) / start.room.y,
            ),
        });
    };

    const nudge = (event: KeyboardEvent<HTMLDivElement>) => {
        const step = event.shiftKey ? 0.1 : 0.02;
        const moves: Record<string, Offset> = {
            ArrowUp: { x: 0, y: step },
            ArrowDown: { x: 0, y: -step },
            ArrowLeft: { x: step, y: 0 },
            ArrowRight: { x: -step, y: 0 },
        };
        const move = moves[event.key];

        if (!move) {
            return;
        }

        event.preventDefault();
        setOffset((current) => ({
            x: clamp(current.x + move.x),
            y: clamp(current.y + move.y),
        }));
    };

    const src = draft?.src ?? url;

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Cover image"
                description="A banner across the top of your profile, shown exactly as it appears here. It waits for a moderator before anyone else sees it unless your commits are approved right away."
            />

            <CoverImage
                lane={user.lane}
                src={src}
                alt={draft ? '' : 'Your cover image'}
                imageStyle={
                    draft
                        ? {
                              objectPosition: `${offset.x * 100}% ${offset.y * 100}%`,
                          }
                        : undefined
                }
                role={draft ? 'img' : undefined}
                aria-label={
                    draft
                        ? 'Cover preview. Drag, or use the arrow keys, to choose which part shows.'
                        : undefined
                }
                tabIndex={draft ? 0 : undefined}
                onPointerDown={startDrag}
                onPointerMove={moveDrag}
                onPointerUp={() => (drag.current = null)}
                onPointerCancel={() => (drag.current = null)}
                onKeyDown={draft ? nudge : undefined}
                className={cn(
                    coverAspect,
                    'rounded-lg border select-none',
                    draft &&
                        'cursor-grab touch-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring active:cursor-grabbing',
                )}
                data-test="cover-image-frame"
            >
                {draft ? (
                    <CoverPill>
                        <Move aria-hidden className="size-3.5" />
                        Drag to reposition
                    </CoverPill>
                ) : (
                    isPending && <CoverPill>Waiting for review</CoverPill>
                )}
            </CoverImage>

            <input
                ref={input}
                type="file"
                accept={acceptedImageTypes.join(',')}
                className="hidden"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = '';

                    if (file) {
                        void pick(file);
                    }
                }}
                data-test="cover-image-input"
            />

            <div className="mt-4 flex flex-wrap gap-2">
                {draft ? (
                    <>
                        <Button
                            className="min-w-28"
                            disabled={processing}
                            aria-busy={processing}
                            onClick={save}
                            data-test="save-cover-button"
                        >
                            {processing && <Spinner />}
                            Save cover
                        </Button>
                        <Button
                            variant="ghost"
                            disabled={processing}
                            onClick={() => {
                                setDraft(null);
                                setError('');
                            }}
                        >
                            Cancel
                        </Button>
                    </>
                ) : (
                    <>
                        <Button
                            variant="outline"
                            className="min-w-36"
                            disabled={processing}
                            aria-busy={processing}
                            onClick={() => input.current?.click()}
                        >
                            {processing ? <Spinner /> : <ImageUp />}
                            {url ? 'Replace cover' : 'Upload cover'}
                        </Button>
                        {url && (
                            <Button
                                variant="ghost"
                                disabled={processing}
                                onClick={() =>
                                    router.delete(destroy.url(), options)
                                }
                            >
                                <Trash2 />
                                Remove
                            </Button>
                        )}
                    </>
                )}
            </div>
            <p className="mt-3 text-sm text-muted-foreground">
                JPEG, PNG, or WebP, at least 875×250 and up to 5 MB.
            </p>
            <InputError className="mt-2" message={error} />
        </section>
    );
}
