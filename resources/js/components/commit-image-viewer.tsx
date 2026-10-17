import { usePage } from '@inertiajs/react';
import {
    PanelBottomClose,
    PanelBottomOpen,
    PanelRightClose,
    PanelRightOpen,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Lightbox from 'yet-another-react-lightbox';
import type { ControllerRef } from 'yet-another-react-lightbox';
import Counter from 'yet-another-react-lightbox/plugins/counter';
import Download from 'yet-another-react-lightbox/plugins/download';
import Zoom from 'yet-another-react-lightbox/plugins/zoom';
import 'yet-another-react-lightbox/plugins/counter.css';
import 'yet-another-react-lightbox/styles.css';
import { show as showCommitImage } from '@/actions/App/Http/Controllers/CommitImageController';
import { CommitCard } from '@/components/commit-card';
import { commitImageAlt } from '@/components/commit-images';
import { isTyping } from '@/hooks/use-keyboard-shortcuts';
import { closeCommitPhoto, showCommitPhoto } from '@/lib/commit-photo';
import type { CommitPhoto } from '@/lib/commit-photo';
import { cn } from '@/lib/utils';

export function CommitImageViewer() {
    const { photo } = usePage<{ photo?: CommitPhoto }>().props;
    const [isPanelOpen, setIsPanelOpen] = useState(true);
    const [captionedImageId, setCaptionedImageId] = useState<string | null>(
        null,
    );
    const controller = useRef<ControllerRef>(null);
    const isOpen = photo !== undefined;
    const images = photo?.commit.images;

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            const lightbox = controller.current;
            const actions: Record<string, (() => void) | undefined> = {
                Escape: lightbox?.close,
                ArrowLeft: lightbox?.prev,
                ArrowRight: lightbox?.next,
            };

            if (
                document.activeElement === document.body &&
                actions[event.key]
            ) {
                event.preventDefault();
                actions[event.key]?.();
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [isOpen]);

    // The lightbox cancels sideways wheel events natively to swipe between photos, before React sees them, so the panel
    // stops them natively too, or its code blocks can't scroll horizontally. Pinches still must not zoom the page.
    const keepWheelInPanel = useCallback((panel: HTMLElement | null) => {
        if (!panel) {
            return;
        }

        const onWheel = (event: WheelEvent) => {
            event.stopPropagation();

            if (event.ctrlKey) {
                event.preventDefault();
            }
        };

        panel.addEventListener('wheel', onWheel, { passive: false });

        return () => panel.removeEventListener('wheel', onWheel);
    }, []);

    const slides = useMemo(
        () =>
            (images ?? []).map((image, index, all) => ({
                src: image.url,
                alt: commitImageAlt(image, index, all.length),
                width: image.width,
                height: image.height,
                download: {
                    url: showCommitImage.url(image.id, {
                        query: { download: 1 },
                    }),
                    filename: `${image.id}.webp`,
                },
            })),
        [images],
    );

    const hasMultipleImages = slides.length > 1;
    const panelLabel = isPanelOpen ? 'Hide commit' : 'Show commit';

    return (
        <Lightbox
            open={isOpen}
            close={() => photo && closeCommitPhoto(photo)}
            index={photo?.index ?? 0}
            slides={slides}
            on={{
                view: ({ index }) => {
                    if (photo && index !== photo.index) {
                        showCommitPhoto(photo, index);
                    }
                },
            }}
            plugins={
                hasMultipleImages ? [Counter, Zoom, Download] : [Zoom, Download]
            }
            className={cn(
                '[--commit-panel-height:0px] [--commit-panel-width:0px]',
                isPanelOpen &&
                    'max-lg:[--commit-panel-height:45svh] lg:[--commit-panel-width:28rem] xl:[--commit-panel-width:32rem]',
            )}
            styles={{
                root: { '--yarl__portal_zindex': 50 },
                container: {
                    backgroundColor: 'rgb(0 0 0 / 0.85)',
                    backdropFilter: 'blur(4px)',
                    paddingRight: 'var(--commit-panel-width)',
                    paddingBottom: 'var(--commit-panel-height)',
                },
                toolbar: { right: 'var(--commit-panel-width)' },
                navigationPrev: {
                    top: 'calc(50% - var(--commit-panel-height) / 2)',
                },
                navigationNext: {
                    top: 'calc(50% - var(--commit-panel-height) / 2)',
                    right: 'var(--commit-panel-width)',
                },
            }}
            carousel={{ finite: true }}
            controller={{
                ref: controller,
                closeOnBackdropClick: true,
                closeOnPullDown: true,
            }}
            toolbar={{
                buttons: [
                    <button
                        key="panel"
                        type="button"
                        className="yarl__button"
                        aria-label={panelLabel}
                        aria-pressed={isPanelOpen}
                        onClick={() => setIsPanelOpen(!isPanelOpen)}
                    >
                        {isPanelOpen ? (
                            <>
                                <PanelBottomClose className="yarl__icon lg:hidden" />
                                <PanelRightClose className="yarl__icon max-lg:hidden" />
                            </>
                        ) : (
                            <>
                                <PanelBottomOpen className="yarl__icon lg:hidden" />
                                <PanelRightOpen className="yarl__icon max-lg:hidden" />
                            </>
                        )}
                    </button>,
                    'close',
                ],
            }}
            render={{
                ...(!hasMultipleImages && {
                    buttonPrev: () => null,
                    buttonNext: () => null,
                }),
                slideFooter: ({ slide }) => {
                    const image = images?.find(({ url }) => url === slide.src);

                    if (!image?.description) {
                        return null;
                    }

                    const isCaptioned = captionedImageId === image.id;
                    const captionId = `commit-image-caption-${image.id}`;

                    return (
                        <div
                            className="absolute inset-x-4 bottom-4 flex flex-col items-start gap-2"
                            onPointerDown={(event) => event.stopPropagation()}
                            onWheel={(event) => event.stopPropagation()}
                        >
                            {isCaptioned && (
                                <p
                                    id={captionId}
                                    className="max-h-40 max-w-xl touch-auto overflow-y-auto overscroll-contain rounded-lg bg-black/80 px-3 py-2 text-sm leading-relaxed whitespace-pre-line text-white ring-1 ring-white/15 select-text"
                                    data-test="commit-image-caption"
                                >
                                    {image.description}
                                </p>
                            )}
                            <button
                                type="button"
                                onClick={() =>
                                    setCaptionedImageId(
                                        isCaptioned ? null : image.id,
                                    )
                                }
                                aria-expanded={isCaptioned}
                                aria-controls={captionId}
                                aria-label={
                                    isCaptioned
                                        ? 'Hide image description'
                                        : 'Show image description'
                                }
                                className="rounded bg-black/75 px-1.5 py-0.5 text-xs font-bold tracking-wide text-white ring-1 ring-white/30 transition hover:bg-black"
                            >
                                ALT
                            </button>
                        </div>
                    );
                },
                controls: () =>
                    photo &&
                    isPanelOpen && (
                        <aside
                            ref={keepWheelInPanel}
                            aria-label="Commit"
                            className="absolute inset-x-0 bottom-0 h-(--commit-panel-height) touch-auto overflow-y-auto overscroll-contain rounded-t-xl border-t bg-card text-card-foreground select-text lg:inset-x-auto lg:inset-y-0 lg:right-0 lg:h-auto lg:w-(--commit-panel-width) lg:rounded-none lg:border-t-0 lg:border-l"
                            onPointerDown={(event) => event.stopPropagation()}
                            onKeyDown={(event) => {
                                if (isTyping(event.target)) {
                                    event.stopPropagation();
                                }
                            }}
                        >
                            <CommitCard
                                key={photo.commit.id}
                                commit={photo.commit}
                                isFocused
                                showImages={false}
                                loadsReplies
                            />
                        </aside>
                    ),
            }}
        />
    );
}
