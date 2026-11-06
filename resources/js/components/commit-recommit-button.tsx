import { router, usePage } from '@inertiajs/react';
import { Repeat2 } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/CommitRecommitController';
import { ActionTooltip } from '@/components/action-tooltip';
import { useOwnsCommit } from '@/hooks/use-owns-commit';
import { signIn } from '@/lib/auth';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

export function CommitRecommitButton({
    commit,
    className,
}: {
    commit: Commit;
    className?: string;
}) {
    const { auth } = usePage().props;
    const ownsCommit = useOwnsCommit();
    const [isRecommitting, setIsRecommitting] = useState(false);

    if (commit.status !== 'approved' || ownsCommit(commit)) {
        return null;
    }

    return (
        <ActionTooltip label="Recommit">
            <button
                type="button"
                onClick={() => {
                    if (auth.user === null) {
                        signIn();

                        return;
                    }

                    setIsRecommitting(true);
                    router.post(
                        store.url(commit.id),
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setIsRecommitting(false),
                        },
                    );
                }}
                disabled={isRecommitting}
                aria-label="Recommit"
                className={cn(
                    'flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:pointer-events-none disabled:opacity-50',
                    className,
                )}
                data-test="recommit-button"
            >
                <Repeat2 className="size-4" />
                <span className="sr-only sm:not-sr-only">Recommit</span>
            </button>
        </ActionTooltip>
    );
}
