import { router, useHttp, usePage } from '@inertiajs/react';
import { Bookmark } from 'lucide-react';
import { createContext, useContext, useState } from 'react';
import { toast } from 'sonner';
import BookmarkController from '@/actions/App/Http/Controllers/BookmarkController';
import { signIn } from '@/lib/auth';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

export const BookmarkRemovalContext = createContext<
    ((id: string) => void) | null
>(null);

export function BookmarkButton({ commit }: { commit: Commit }) {
    const { auth } = usePage().props;
    const [isSaved, setIsSaved] = useState(commit.is_saved ?? false);
    const request = useHttp<Record<string, never>, { is_saved: boolean }>({});
    const onRemoved = useContext(BookmarkRemovalContext);

    if (commit.status !== 'approved' && commit.user_id !== auth.user?.id) {
        return null;
    }

    const toggle = async () => {
        if (auth.user === null) {
            signIn();

            return;
        }

        if (request.processing) {
            return;
        }

        try {
            const response = await request.submit(
                isSaved
                    ? BookmarkController.destroy(commit)
                    : BookmarkController.store(commit),
            );

            setIsSaved(response.is_saved);
            router.flushAll();

            if (!response.is_saved) {
                onRemoved?.(commit.id);
            }
        } catch {
            toast.error('Could not update your saved commits. Try again.');
        }
    };

    return (
        <button
            type="button"
            onClick={toggle}
            aria-disabled={request.processing}
            aria-label={isSaved ? 'Unsave commit' : 'Save commit'}
            aria-pressed={isSaved}
            title={isSaved ? 'Unsave commit' : 'Save commit privately'}
            className={cn(
                'rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-disabled:pointer-events-none aria-disabled:opacity-50',
                { 'text-primary hover:text-primary': isSaved },
            )}
            data-test="bookmark-button"
        >
            <Bookmark
                aria-hidden
                className={cn('size-4', { 'fill-current': isSaved })}
            />
        </button>
    );
}
