import { router, useHttp } from '@inertiajs/react';
import { FolderPlus } from 'lucide-react';
import { createContext, useContext, useState } from 'react';
import { toast } from 'sonner';
import BookmarkCollectionCommitController from '@/actions/App/Http/Controllers/BookmarkCollectionCommitController';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { BookmarkCollection, Commit } from '@/types';

export const BookmarkCollectionsContext = createContext<{
    collections: BookmarkCollection[];
    selectedId: string | null;
    onRemovedFromSelected: (id: string) => void;
} | null>(null);

export function BookmarkCollectionsMenu({ commit }: { commit: Commit }) {
    const context = useContext(BookmarkCollectionsContext);
    const [collectionIds, setCollectionIds] = useState(
        commit.collection_ids ?? [],
    );
    const request = useHttp<Record<string, never>, { in_collection: boolean }>(
        {},
    );

    if (context === null || commit.collection_ids === undefined) {
        return null;
    }

    const { collections, selectedId, onRemovedFromSelected } = context;
    const count = collectionIds.filter((id) =>
        collections.some((collection) => collection.id === id),
    ).length;

    const toggle = async (collection: BookmarkCollection) => {
        if (request.processing) {
            return;
        }

        const isAdding = !collectionIds.includes(collection.id);
        const previous = collectionIds;
        const args = { bookmarkCollection: collection.id, commit: commit.id };

        setCollectionIds(
            isAdding
                ? [...previous, collection.id]
                : previous.filter((id) => id !== collection.id),
        );

        try {
            await request.submit(
                isAdding
                    ? BookmarkCollectionCommitController.store(args)
                    : BookmarkCollectionCommitController.destroy(args),
            );

            router.reload({ only: ['collections'] });
            toast.success(
                isAdding
                    ? `Added to ${collection.name}.`
                    : `Removed from ${collection.name}.`,
            );

            if (!isAdding && collection.id === selectedId) {
                onRemovedFromSelected(commit.id);
            }
        } catch {
            setCollectionIds(previous);
            toast.error('Could not update your collections. Try again.');
        }
    };

    const label =
        count === 0
            ? 'Add to collection'
            : `In ${count} ${count === 1 ? 'collection' : 'collections'}`;

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={label}
                    title={label}
                    className={cn(
                        'rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:bg-accent',
                        { 'text-primary hover:text-primary': count > 0 },
                    )}
                    data-test="bookmark-collections-button"
                >
                    <FolderPlus aria-hidden className="size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-[min(20rem,60dvh)] w-60 overflow-y-auto"
            >
                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                    Collections
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {collections.length === 0 ? (
                    <p className="px-2 py-1.5 text-sm text-muted-foreground">
                        No collections yet. Create one above your saved commits.
                    </p>
                ) : (
                    collections.map((collection) => (
                        <DropdownMenuCheckboxItem
                            key={collection.id}
                            checked={collectionIds.includes(collection.id)}
                            onSelect={(event) => {
                                event.preventDefault();
                                void toggle(collection);
                            }}
                            title={collection.name}
                            data-test="bookmark-collection-option"
                        >
                            <span className="min-w-0 truncate">
                                {collection.name}
                            </span>
                        </DropdownMenuCheckboxItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
