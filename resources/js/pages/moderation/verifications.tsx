import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { Check, GitBranch, X } from 'lucide-react';
import { useState } from 'react';
import { store as approve } from '@/actions/App/Http/Controllers/BranchVerificationApprovalController';
import { store as reject } from '@/actions/App/Http/Controllers/BranchVerificationRejectionController';
import { EmptyFeed } from '@/components/commit-feed';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { show as branchShow } from '@/routes/branch';
import { show as userShow } from '@/routes/user';
import type { CursorPaginated } from '@/types';

type Maintainer = {
    username: string;
    name: string;
    avatar_url: string;
    lane: string;
};

type Verification = {
    name: string;
    domain: string | null;
    verification_requested_at: string;
    txt: {
        host: string | null;
        expected: string | null;
        records: string[];
        matches: boolean;
    };
    maintainers: Maintainer[];
};

export default function Verifications({
    verifications,
}: {
    verifications: CursorPaginated<Verification>;
}) {
    const [busy, setBusy] = useState(false);
    const resolve = (
        route: ReturnType<typeof approve> | ReturnType<typeof reject>,
    ) =>
        router.visit(route, {
            preserveScroll: true,
            reset: ['verifications'],
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });

    return (
        <>
            <Head title="Branch verifications" />
            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                    <p className="ml-auto text-sm text-muted-foreground">
                        Oldest first
                    </p>
                </div>
                <ModerationTabs active="verifications" />
            </div>
            <p className="border-b px-4 py-3 text-sm text-muted-foreground">
                Domain ownership requests. Approve only when the TXT record
                matches; daily rechecks drop the badge if it disappears.
            </p>
            {verifications.data.length === 0 ? (
                <div className="px-6 py-16 text-center">
                    <EmptyFeed command="nothing to review">
                        No verification requests are waiting.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="verifications"
                    onlyNext
                    preserveUrl
                    loading={
                        <p
                            className="p-4 text-sm text-muted-foreground"
                            role="status"
                        >
                            Loading verifications…
                        </p>
                    }
                >
                    {verifications.data.map((item) => (
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
                                        {item.domain ?? 'No domain'}
                                        {' · '}
                                        requested{' '}
                                        <time
                                            dateTime={
                                                item.verification_requested_at
                                            }
                                        >
                                            {new Date(
                                                item.verification_requested_at,
                                            ).toLocaleString()}
                                        </time>
                                    </p>
                                </div>
                                <span
                                    className={
                                        item.txt.matches
                                            ? 'rounded-md bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-400'
                                            : 'rounded-md bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive'
                                    }
                                >
                                    {item.txt.matches
                                        ? 'TXT match'
                                        : 'TXT missing'}
                                </span>
                            </div>

                            {item.maintainers.length > 0 && (
                                <ul className="flex flex-wrap gap-3">
                                    {item.maintainers.map((maintainer) => (
                                        <li key={maintainer.username}>
                                            <Link
                                                href={userShow(
                                                    maintainer.username,
                                                )}
                                                className="flex items-center gap-2 hover:opacity-90"
                                            >
                                                <UserAvatar
                                                    user={maintainer}
                                                    className="size-6"
                                                />
                                                <span className="font-mono text-[13px] text-muted-foreground hover:underline">
                                                    @{maintainer.username}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div className="space-y-2 rounded-md border p-3 font-mono text-[12px]">
                                <p>
                                    <span className="text-muted-foreground">
                                        Host:{' '}
                                    </span>
                                    {item.txt.host ?? '—'}
                                </p>
                                <p className="break-all">
                                    <span className="text-muted-foreground">
                                        Expected:{' '}
                                    </span>
                                    {item.txt.expected ?? '—'}
                                </p>
                                <div>
                                    <p className="text-muted-foreground">
                                        Found:
                                    </p>
                                    {item.txt.records.length === 0 ? (
                                        <p className="mt-1 text-destructive">
                                            (none)
                                        </p>
                                    ) : (
                                        <ul className="mt-1 space-y-1">
                                            {item.txt.records.map((record) => (
                                                <li
                                                    key={record}
                                                    className="break-all"
                                                >
                                                    {record}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy || !item.txt.matches}
                                    onClick={() =>
                                        resolve(approve(item.name))
                                    }
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

Verifications.layout = AppLayout;
