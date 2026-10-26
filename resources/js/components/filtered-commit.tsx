import { useHttp } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { show } from '@/actions/App/Http/Controllers/CommitVisibilityController';
import { CommitCard } from '@/components/commit-card';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { Commit } from '@/types';

export function FilteredCommit({
    commitId,
    isReply = false,
}: {
    commitId: string;
    isReply?: boolean;
}) {
    const [commit, setCommit] = useState<Commit | null>(null);
    const [open, setOpen] = useState(false);
    const titleRef = useRef<HTMLHeadingElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const request = useHttp<Record<string, never>, Commit>({});

    const reveal = async () => {
        if (request.processing) {
            return;
        }

        try {
            const revealed = await request.submit(show(commitId));
            setCommit(revealed);
            setOpen(true);
        } catch {
            toast.error('Could not show this commit. Try again.');
        }
    };

    return (
        <>
            <div
                className="flex flex-wrap items-center gap-x-3 text-sm text-muted-foreground"
                data-test="filtered-commit"
            >
                <p>
                    {isReply ? 'Reply' : 'Commit'} hidden by your visibility
                    settings.
                </p>
                <button
                    ref={buttonRef}
                    type="button"
                    onClick={() => void reveal()}
                    disabled={request.processing}
                    aria-busy={request.processing}
                    className="flex min-h-11 shrink-0 items-center gap-1.5 rounded-md px-2 text-primary transition-colors hover:bg-primary-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50"
                    data-test="show-filtered-commit"
                >
                    {request.processing ? (
                        <Spinner className="size-3.5" aria-hidden />
                    ) : (
                        <Eye className="size-3.5" aria-hidden />
                    )}
                    Show {isReply ? 'reply' : 'commit'}
                </button>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent
                    className="flex flex-col gap-0 overflow-hidden bg-card p-0 motion-reduce:animate-none sm:max-w-2xl"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        titleRef.current?.focus();
                    }}
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        buttonRef.current?.focus();
                    }}
                    aria-describedby={undefined}
                    data-test="revealed-commit"
                >
                    <DialogTitle
                        ref={titleRef}
                        tabIndex={-1}
                        className="shrink-0 border-b px-5 py-4 pr-12 text-base outline-none"
                    >
                        {isReply ? 'Hidden reply' : 'Hidden commit'}
                    </DialogTitle>
                    <div className="min-h-0 overflow-y-auto overscroll-contain px-4 py-2 sm:px-5">
                        {commit && (
                            <CommitCard
                                commit={commit}
                                isFolded={false}
                                hasRailTail={false}
                            />
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
