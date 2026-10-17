import { Head, Link, router } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import { useRef } from 'react';
import BookmarkController from '@/actions/App/Http/Controllers/BookmarkController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { BookmarkRemovalContext } from '@/components/bookmark-button';
import {
    BookmarkCollectionBar,
    BookmarkCollectionHeader,
} from '@/components/bookmark-collections';
import { BookmarkCollectionsContext } from '@/components/bookmark-collections-menu';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { focusCommit } from '@/hooks/use-keyboard-shortcuts';
import AppLayout from '@/layouts/app-layout';
import type { BookmarkCollection, Commit, CursorPaginated } from '@/types';

export default function Index({
    commits,
    collections,
    collection,
}: {
    commits: CursorPaginated<Commit>;
    collections: BookmarkCollection[];
    collection: Pick<BookmarkCollection, 'id' | 'name'> | null;
}) {
    const headingRef = useRef<HTMLHeadingElement>(null);
    const remove = (id: string) => {
        const index = commits.data.findIndex((commit) => commit.id === id);

        if (index === -1) {
            return;
        }

        const next = commits.data[index + 1] ?? commits.data[index - 1];
        const current = document.querySelector(`[data-commit="${id}"]`);

        if (current?.contains(document.activeElement)) {
            const target = next
                ? document.querySelector<HTMLElement>(
                      `[data-commit="${next.id}"]`,
                  )
                : headingRef.current;

            if (target) {
                focusCommit(target);
            }
        }

        if (commits.data.length === 1 && commits.next_cursor !== null) {
            router.reload({ only: ['commits'], reset: ['commits'] });
        } else {
            router.replaceProp('commits.data', (data: Commit[]) =>
                data.filter((commit) => commit.id !== id),
            );
        }
    };
    const removeAndRecount = (id: string) => {
        remove(id);

        if (collections.length > 0) {
            router.reload({ only: ['collections'] });
        }
    };

    return (
        <>
            <Head title={collection ? `${collection.name} · Saved` : 'Saved'} />

            <header className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 ref={headingRef} tabIndex={-1} className="font-semibold">
                    Saved
                </h1>
                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <LockKeyhole aria-hidden className="size-3.5" />
                    Only you
                </span>
            </header>

            <BookmarkCollectionBar
                collections={collections}
                selectedId={collection?.id ?? null}
            />

            {collection ? (
                <BookmarkCollectionHeader
                    key={collection.id}
                    collection={collection}
                />
            ) : (
                <p className="border-b px-4 py-3 text-sm text-muted-foreground sm:px-6">
                    Commits you want to come back to. Most recently saved first.
                </p>
            )}

            <BookmarkRemovalContext value={removeAndRecount}>
                <BookmarkCollectionsContext
                    value={{
                        collections,
                        selectedId: collection?.id ?? null,
                        onRemovedFromSelected: remove,
                    }}
                >
                    <CommitFeed
                        key={collection?.id ?? 'all'}
                        commits={commits}
                        end={
                            collection
                                ? "You've reached the end of this collection."
                                : "You've reached the end of your saved commits."
                        }
                        empty={
                            collection ? (
                                <EmptyFeed command="fatal: nothing in this collection yet">
                                    Open the collections menu on any saved
                                    commit to add it here.
                                    <Link
                                        href={BookmarkController.index()}
                                        className="mt-3 block text-primary hover:underline"
                                    >
                                        Back to all saved
                                    </Link>
                                </EmptyFeed>
                            ) : (
                                <EmptyFeed command="fatal: no saved commits yet">
                                    Save a commit to find it here. Your saved
                                    commits are private.
                                    <Link
                                        href={LogController.show('trending')}
                                        className="mt-3 block text-primary hover:underline"
                                    >
                                        Explore Trending
                                    </Link>
                                </EmptyFeed>
                            )
                        }
                    />
                </BookmarkCollectionsContext>
            </BookmarkRemovalContext>
        </>
    );
}

Index.layout = AppLayout;
