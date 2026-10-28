import { useEffect, useState } from 'react';
import { QuotedCommit } from '@/components/quoted-commit';
import { Skeleton } from '@/components/ui/skeleton';
import type { QuotedCommit as QuotedCommitData } from '@/types';

export function CommitLinkPreview({ url }: { url: string }) {
    const [commit, setCommit] = useState<QuotedCommitData | null>();

    useEffect(() => {
        const controller = new AbortController();

        void fetch(url, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        })
            .then(async (response) => {
                const data = response.ok
                    ? ((await response.json()) as { commit: QuotedCommitData })
                    : null;

                if (!controller.signal.aborted) {
                    setCommit(data?.commit ?? null);
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setCommit(null);
                }
            });

        return () => controller.abort();
    }, [url]);

    if (commit === undefined) {
        return (
            <div
                aria-busy="true"
                aria-label="Loading linked commit"
                className="mt-2 animate-in rounded-lg border px-3 py-2 delay-300 fill-mode-backwards fade-in"
                data-test="commit-link-preview-skeleton"
            >
                <div className="flex items-center gap-1.5">
                    <Skeleton className="size-5 rounded-full" />
                    <Skeleton className="h-3 w-32" />
                </div>
                <Skeleton className="mt-2 h-4 w-full" />
                <Skeleton className="mt-2 h-4 w-2/3" />
            </div>
        );
    }

    return <QuotedCommit commit={commit} label="Linked commit" />;
}
