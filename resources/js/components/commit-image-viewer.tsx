import { PanelRightClose, PanelRightOpen } from 'lucide-react';
import type { ReactNode } from 'react';
import { Suspense, use, useState } from 'react';
import Lightbox from 'yet-another-react-lightbox';
import type { SlideImage } from 'yet-another-react-lightbox';
import Counter from 'yet-another-react-lightbox/plugins/counter';
import Zoom from 'yet-another-react-lightbox/plugins/zoom';
import 'yet-another-react-lightbox/plugins/counter.css';
import 'yet-another-react-lightbox/styles.css';
import { CommitCard } from '@/components/commit-card';
import { CommitImageViewerContext } from '@/components/commit-images';
import { fetchReplies } from '@/components/commit-thread';
import { isTyping } from '@/hooks/use-keyboard-shortcuts';
import { cn } from '@/lib/utils';
import type { Commit, CursorPaginated, ThreadCommit } from '@/types';

type Replies = Promise<CursorPaginated<ThreadCommit> | undefined>;

type Viewing = {
    commit: Commit;
    slides: SlideImage[];
    index: number;
    replies: Replies;
};

export function CommitImageViewer({ children }: { children: ReactNode }) {
    const [viewing, setViewing] = useState<Viewing | null>(null);
    const [isOpen, setIsOpen] = useState(false);
    const [isPanelOpen, setIsPanelOpen] = useState(true);

    const open = (commit: Commit, index: number) => {
        setViewing({
            commit,
            slides: (commit.images ?? []).map((image) => ({
                src: image.url,
                width: image.width,
                height: image.height,
            })),
            index,
            replies: commit.replies_count
                ? fetchReplies(commit, null).catch(() => undefined)
                : Promise.resolve(undefined),
        });
        setIsOpen(true);
    };

    const hasMultipleImages = (viewing?.slides.length ?? 0) > 1;
    const PanelIcon = isPanelOpen ? PanelRightClose : PanelRightOpen;

    return (
        <CommitImageViewerContext value={open}>
            {children}

            <Lightbox
                open={isOpen}
                close={() => setIsOpen(false)}
                index={viewing?.index ?? 0}
                slides={viewing?.slides ?? []}
                plugins={hasMultipleImages ? [Counter, Zoom] : [Zoom]}
                className={cn(
                    '[--commit-panel-width:0px]',
                    isPanelOpen && 'lg:[--commit-panel-width:26rem]',
                )}
                styles={{
                    root: { '--yarl__portal_zindex': 50 },
                    container: {
                        backgroundColor: 'rgb(0 0 0 / 0.85)',
                        backdropFilter: 'blur(4px)',
                        paddingRight: 'var(--commit-panel-width)',
                    },
                    toolbar: { right: 'var(--commit-panel-width)' },
                    navigationNext: { right: 'var(--commit-panel-width)' },
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
                            className="yarl__button max-lg:hidden"
                            aria-label={
                                isPanelOpen ? 'Hide commit' : 'Show commit'
                            }
                            aria-pressed={isPanelOpen}
                            onClick={() => setIsPanelOpen(!isPanelOpen)}
                        >
                            <PanelIcon className="yarl__icon" />
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
                        viewing &&
                        isPanelOpen && (
                            <aside
                                aria-label="Commit"
                                className="absolute inset-y-0 right-0 w-(--commit-panel-width) touch-auto overflow-y-auto overscroll-contain border-l bg-card text-card-foreground select-text max-lg:hidden"
                                onPointerDown={(event) =>
                                    event.stopPropagation()
                                }
                                onWheel={(event) => event.stopPropagation()}
                                onKeyDown={(event) => {
                                    if (isTyping(event.target)) {
                                        event.stopPropagation();
                                    }
                                }}
                            >
                                <Suspense
                                    key={viewing.commit.id}
                                    fallback={
                                        <CommitCard
                                            commit={viewing.commit}
                                            isFocused
                                            showImages={false}
                                        />
                                    }
                                >
                                    <ViewedCommit
                                        commit={viewing.commit}
                                        replies={viewing.replies}
                                    />
                                </Suspense>
                            </aside>
                        ),
                }}
            />
        </CommitImageViewerContext>
    );
}

function ViewedCommit({
    commit,
    replies,
}: {
    commit: Commit;
    replies: Replies;
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
