import { useHttp } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { show } from '@/actions/App/Http/Controllers/CommitVisibilityController';
import { Spinner } from '@/components/ui/spinner';
import type { Commit } from '@/types';

export function useCommitVisibility<
    T extends Pick<Commit, 'id' | 'is_filtered'>,
>(original: T) {
    const [revealed, setRevealed] = useState<{
        original: T;
        commit: Commit;
    } | null>(null);
    const request = useHttp<Record<string, never>, Commit>({});

    const reveal = async () => {
        if (request.processing) {
            return;
        }

        try {
            const commit = await request.submit(show(original.id));
            setRevealed({ original, commit });
        } catch {
            toast.error('Could not show this commit. Try again.');
        }
    };

    return {
        commit:
            original.is_filtered && revealed?.original === original
                ? { ...original, ...revealed.commit }
                : original,
        reveal,
        processing: request.processing,
    };
}

export function FilteredCommit({
    isReply = false,
    processing,
    onReveal,
}: {
    isReply?: boolean;
    processing: boolean;
    onReveal: () => void;
}) {
    return (
        <div
            className="flex flex-wrap items-center gap-x-3 text-sm text-muted-foreground"
            data-test="filtered-commit"
        >
            <p>
                {isReply ? 'Reply' : 'Commit'} hidden by your visibility
                settings.
            </p>
            <button
                type="button"
                onClick={onReveal}
                disabled={processing}
                aria-busy={processing}
                className="flex min-h-11 shrink-0 items-center gap-1.5 rounded-md px-2 text-primary transition-colors hover:bg-primary-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50"
                data-test="show-filtered-commit"
            >
                {processing ? (
                    <Spinner className="size-3.5" aria-hidden />
                ) : (
                    <Eye className="size-3.5" aria-hidden />
                )}
                Show {isReply ? 'reply' : 'commit'}
            </button>
        </div>
    );
}
