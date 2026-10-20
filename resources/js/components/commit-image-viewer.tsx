import { usePage } from '@inertiajs/react';
import {
    PanelBottomClose,
    PanelBottomOpen,
    PanelRightClose,
    PanelRightOpen,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import { show as showCommitImage } from '@/actions/App/Http/Controllers/CommitImageController';
import { CommitCard } from '@/components/commit-card';
import type { ShownImage } from '@/components/commit-images';
import { ImageViewer } from '@/components/image-viewer';
import { isTyping } from '@/hooks/use-keyboard-shortcuts';
import { closeCommitPhoto, showCommitPhoto } from '@/lib/commit-photo';
import type { CommitPhoto } from '@/lib/commit-photo';
import { cn } from '@/lib/utils';

const downloadUrl = (image: ShownImage): string =>
    showCommitImage.url(image.id, { query: { download: 1 } });

export function CommitImageViewer() {
    const { photo } = usePage<{ photo?: CommitPhoto }>().props;
    const [isPanelOpen, setIsPanelOpen] = useState(true);

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

    const panelLabel = isPanelOpen ? 'Hide commit' : 'Show commit';

    return (
        <ImageViewer
            key={photo?.commit.id}
            images={photo?.commit.images ?? []}
            index={photo?.index ?? null}
            onView={(index) => photo && showCommitPhoto(photo, index)}
            onClose={() => photo && closeCommitPhoto(photo)}
            downloadUrl={downloadUrl}
            className={cn(
                '[--commit-panel-height:0px] [--commit-panel-width:0px]',
                isPanelOpen &&
                    'max-lg:[--commit-panel-height:45svh] lg:[--commit-panel-width:28rem] xl:[--commit-panel-width:32rem]',
            )}
            styles={{
                container: {
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
            toolbarButtons={[
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
            ]}
            controls={() =>
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
                            endsWithDot
                        />
                    </aside>
                )
            }
        />
    );
}
