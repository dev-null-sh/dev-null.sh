import { usePage } from '@inertiajs/react';
import {
    PanelBottomClose,
    PanelBottomOpen,
    PanelRightClose,
    PanelRightOpen,
} from 'lucide-react';
import { Suspense, use, useMemo, useState } from 'react';
import Lightbox from 'yet-another-react-lightbox';
import Counter from 'yet-another-react-lightbox/plugins/counter';
import Zoom from 'yet-another-react-lightbox/plugins/zoom';
import 'yet-another-react-lightbox/plugins/counter.css';
import 'yet-another-react-lightbox/styles.css';
import { CommitCard } from '@/components/commit-card';
import { isTyping } from '@/hooks/use-keyboard-shortcuts';
import {
    closeCommitPhoto,
    repliesFor,
    showCommitPhoto,
} from '@/lib/commit-photo';
import type { CommitPhoto, CommitPhotoReplies } from '@/lib/commit-photo';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

export function CommitImageViewer() {
    const { photo } = usePage<{ photo?: CommitPhoto }>().props;
    const [isPanelOpen, setIsPanelOpen] = useState(true);
    const images = photo?.commit.images;

    const slides = useMemo(
        () =>
            (images ?? []).map((image) => ({
                src: image.url,
                width: image.width,
                height: image.height,
            })),
        [images],
    );

    const hasMultipleImages = slides.length > 1;
    const panelLabel = isPanelOpen ? 'Hide commit' : 'Show commit';

    return (
        <Lightbox
            open={photo !== undefined}
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
            plugins={hasMultipleImages ? [Counter, Zoom] : [Zoom]}
            className={cn(
                '[--commit-panel-height:0px] [--commit-panel-width:0px]',
                isPanelOpen &&
                    'max-lg:[--commit-panel-height:45svh] lg:[--commit-panel-width:26rem]',
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
                controls: () =>
                    photo &&
                    isPanelOpen && (
                        <aside
                            aria-label="Commit"
                            className="absolute inset-x-0 bottom-0 h-(--commit-panel-height) touch-auto overflow-y-auto overscroll-contain rounded-t-xl border-t bg-card text-card-foreground select-text lg:inset-x-auto lg:inset-y-0 lg:right-0 lg:h-auto lg:w-(--commit-panel-width) lg:rounded-none lg:border-t-0 lg:border-l"
                            onPointerDown={(event) => event.stopPropagation()}
                            onWheel={(event) => event.stopPropagation()}
                            onKeyDown={(event) => {
                                if (isTyping(event.target)) {
                                    event.stopPropagation();
                                }
                            }}
                        >
                            <Suspense
                                key={photo.commit.id}
                                fallback={
                                    <CommitCard
                                        commit={photo.commit}
                                        isFocused
                                        showImages={false}
                                    />
                                }
                            >
                                <ViewedCommit
                                    commit={photo.commit}
                                    replies={repliesFor(photo.commit)}
                                />
                            </Suspense>
                        </aside>
                    ),
            }}
        />
    );
}

function ViewedCommit({
    commit,
    replies,
}: {
    commit: Commit;
    replies: CommitPhotoReplies;
}) {
    return (
        <CommitCard
            commit={commit}
            isFocused
            showImages={false}
            initialReplies={use(replies)}
        />
    );
}
