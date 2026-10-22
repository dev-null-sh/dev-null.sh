import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { store as approve } from '@/actions/App/Http/Controllers/UserCoverImageApprovalController';
import { store as reject } from '@/actions/App/Http/Controllers/UserCoverImageRejectionController';
import { EmptyFeed } from '@/components/commit-feed';
import { CoverImage, coverAspect } from '@/components/cover-image';
import { ModerationHeader } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { CursorPaginated, User } from '@/types';

type PendingCover = {
    user: User;
    url: string;
    version: string;
};

export default function Covers({
    covers,
}: {
    covers: CursorPaginated<PendingCover>;
}) {
    const [busy, setBusy] = useState(false);

    const moderate = (route: ReturnType<typeof approve>, version: string) =>
        router.visit(route, {
            data: { version },
            preserveScroll: true,
            only: ['covers'],
            reset: ['covers'],
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onHttpException: (response) => {
                if (response.status !== 404) {
                    return;
                }

                toast('This cover was replaced or removed. Reloading.');
                router.reload({ only: ['covers'], reset: ['covers'] });

                return false;
            },
        });

    return (
        <>
            <Head title="Cover images" />
            <ModerationHeader active="covers" />
            <p className="border-b px-4 py-3 text-sm text-muted-foreground">
                Profile cover images waiting for review. A rejected cover is
                erased, and the profile goes back to its lane color.
            </p>
            {covers.data.length === 0 ? (
                <div className="px-6 py-16 text-center">
                    <EmptyFeed command="nothing to review">
                        No cover images are waiting for review.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="covers"
                    onlyNext
                    preserveUrl
                    loading={
                        <p
                            className="p-4 text-sm text-muted-foreground"
                            role="status"
                        >
                            Loading cover images…
                        </p>
                    }
                >
                    {covers.data.map((cover) => (
                        <article
                            key={cover.user.id}
                            className="grid gap-3 border-b px-4 py-4"
                            data-test="pending-cover"
                        >
                            <Link
                                href={show(cover.user)}
                                className="flex min-w-0 items-center gap-2 text-sm"
                            >
                                <UserAvatar
                                    user={cover.user}
                                    className="size-6"
                                    pixels={48}
                                />
                                <span className="truncate font-medium">
                                    {cover.user.name}
                                </span>
                                <span
                                    className="truncate font-mono text-muted-foreground"
                                    title={`@${cover.user.username}`}
                                >
                                    @{cover.user.username}
                                </span>
                            </Link>
                            <CoverImage
                                lane={cover.user.lane}
                                src={cover.url}
                                alt={`Cover image uploaded by @${cover.user.username}`}
                                className={cn(coverAspect, 'rounded-md border')}
                            />
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    disabled={busy}
                                    onClick={() =>
                                        moderate(
                                            approve(cover.user),
                                            cover.version,
                                        )
                                    }
                                    data-test="approve-cover-button"
                                >
                                    <Check />
                                    Approve
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() =>
                                        moderate(
                                            reject(cover.user),
                                            cover.version,
                                        )
                                    }
                                    data-test="reject-cover-button"
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

Covers.layout = AppLayout;
