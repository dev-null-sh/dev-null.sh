import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { Check, GitBranch, X } from 'lucide-react';
import { useState } from 'react';
import { store as approve } from '@/actions/App/Http/Controllers/BranchClaimApprovalController';
import { store as reject } from '@/actions/App/Http/Controllers/BranchClaimRejectionController';
import { EmptyFeed } from '@/components/commit-feed';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { show as branchShow } from '@/routes/branch';
import { show as userShow } from '@/routes/user';
import type { CursorPaginated } from '@/types';

type Requester = {
    username: string;
    name: string;
    avatar_url: string;
    lane: string;
};

type Claim = {
    name: string;
    claim_requested_at: string;
    requester: Requester | null;
};

export default function Claims({
    claims,
}: {
    claims: CursorPaginated<Claim>;
}) {
    const [busy, setBusy] = useState(false);
    const resolve = (
        route: ReturnType<typeof approve> | ReturnType<typeof reject>,
    ) =>
        router.visit(route, {
            preserveScroll: true,
            reset: ['claims'],
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });

    return (
        <>
            <Head title="Branch claims" />
            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                    <p className="ml-auto text-sm text-muted-foreground">
                        Oldest first
                    </p>
                </div>
                <ModerationTabs active="claims" />
            </div>
            <p className="border-b px-4 py-3 text-sm text-muted-foreground">
                Ownership requests for casual and org branches. Approve to grant
                maintainer access; domain verification is a separate step.
            </p>
            {claims.data.length === 0 ? (
                <div className="px-6 py-16 text-center">
                    <EmptyFeed command="nothing to review">
                        No ownership claims are waiting.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="claims"
                    onlyNext
                    preserveUrl
                    loading={
                        <p
                            className="p-4 text-sm text-muted-foreground"
                            role="status"
                        >
                            Loading claims…
                        </p>
                    }
                >
                    {claims.data.map((item) => (
                        <article
                            key={item.name}
                            className="space-y-3 border-b px-4 py-4"
                        >
                            <div className="flex flex-wrap items-start gap-3">
                                <div className="min-w-0 flex-1 space-y-1">
                                    <Link
                                        href={branchShow(item.name)}
                                        className="inline-flex items-center gap-1.5 font-mono text-sm font-semibold hover:underline"
                                    >
                                        <GitBranch
                                            className="size-3.5 shrink-0"
                                            aria-hidden
                                        />
                                        {item.name}
                                    </Link>
                                    <p className="text-sm text-muted-foreground">
                                        requested{' '}
                                        <time
                                            dateTime={item.claim_requested_at}
                                        >
                                            {new Date(
                                                item.claim_requested_at,
                                            ).toLocaleString()}
                                        </time>
                                    </p>
                                </div>
                            </div>

                            {item.requester && (
                                <Link
                                    href={userShow(item.requester.username)}
                                    className="flex w-fit items-center gap-2 hover:opacity-90"
                                >
                                    <UserAvatar
                                        user={item.requester}
                                        className="size-6"
                                    />
                                    <span className="font-mono text-[13px] text-muted-foreground hover:underline">
                                        @{item.requester.username}
                                    </span>
                                </Link>
                            )}

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => resolve(approve(item.name))}
                                >
                                    <Check />
                                    Approve
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => resolve(reject(item.name))}
                                >
                                    <X />
                                    Reject
                                </Button>
                            </div>
                        </article>
                    ))}
                </InfiniteScroll>
            )}
        </>
    );
}

Claims.layout = AppLayout;
