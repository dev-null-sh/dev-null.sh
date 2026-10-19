import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { UserAvatar } from '@/components/user-avatar';
import {
    ChatRequestError,
    listBroadcastMembers,
    moderateBroadcastMember,
} from '@/lib/chat';
import type {
    BroadcastMember,
    BroadcastMemberPage,
    BroadcastRef,
} from '@/types';

type Moderation = { member: BroadcastMember; action: 'kick' | 'ban' | 'unban' };

/** The owner-only roster. Subscriber identities never enter another member's thread payload. */
export function BroadcastMembers({ broadcast }: { broadcast: BroadcastRef }) {
    const [query, setQuery] = useState('');
    const [revision, setRevision] = useState(0);
    const [found, setFound] = useState<
        (BroadcastMemberPage & { term: string }) | null
    >(null);
    const [error, setError] = useState<string | null>(null);
    const [confirmation, setConfirmation] = useState<Moderation | null>(null);
    const [isBusy, setIsBusy] = useState(false);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const controller = useRef<AbortController | null>(null);
    const term = query.trim();
    const page = found?.term === term ? found : null;

    useEffect(() => {
        controller.current = new AbortController();
        const signal = controller.current.signal;
        const timer = window.setTimeout(
            () => {
                setError(null);
                void listBroadcastMembers({ id: broadcast.id }, term, signal)
                    .then((result) => {
                        if (!signal.aborted) setFound({ ...result, term });
                    })
                    .catch((failure: unknown) => {
                        if (!signal.aborted) {
                            setError(
                                failure instanceof Error
                                    ? failure.message
                                    : 'Could not load members.',
                            );
                        }
                    });
            },
            term === '' ? 0 : 150,
        );
        return () => {
            window.clearTimeout(timer);
            controller.current?.abort();
        };
    }, [broadcast.id, term, revision]);

    const loadMore = async () => {
        if (page?.next_cursor === null || page === null || isLoadingMore)
            return;
        controller.current?.abort();
        controller.current = new AbortController();
        const signal = controller.current.signal;
        setIsLoadingMore(true);
        setError(null);
        try {
            const result = await listBroadcastMembers(
                broadcast,
                term,
                signal,
                page.next_cursor,
            );
            if (!signal.aborted) {
                setFound({
                    ...result,
                    term,
                    members: [...page.members, ...result.members],
                });
            }
        } catch (failure) {
            if (!signal.aborted)
                setError(
                    failure instanceof Error
                        ? failure.message
                        : 'Could not load members.',
                );
        } finally {
            setIsLoadingMore(false);
        }
    };

    const moderate = async () => {
        if (confirmation === null || isBusy) return;
        setIsBusy(true);
        try {
            await moderateBroadcastMember(
                broadcast,
                confirmation.member.user.username,
                confirmation.action,
            );
            setConfirmation(null);
            setRevision((value) => value + 1);
        } catch (failure) {
            toast.error(
                failure instanceof ChatRequestError
                    ? failure.firstError()
                    : 'Could not update this member.',
            );
        } finally {
            setIsBusy(false);
        }
    };

    return (
        <section
            className="space-y-2 border-t px-5 py-3"
            data-test="broadcast-members"
        >
            <div className="flex items-center justify-between gap-2">
                <h3 className="font-mono text-xs text-muted-foreground">
                    Members and bans
                </h3>
                <Button
                    variant="ghost"
                    className="h-8 text-xs"
                    onClick={() => setRevision((value) => value + 1)}
                >
                    Refresh
                </Button>
            </div>
            <label className="block">
                <span className="sr-only">Search members</span>
                <input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search names or @handles"
                    maxLength={100}
                    className="h-9 w-full rounded-md border bg-card px-3 text-base outline-none focus-visible:border-primary md:text-sm"
                />
            </label>
            <p
                role="alert"
                className="min-h-4 font-mono text-xs text-destructive"
            >
                {error}
            </p>
            <ul className="min-h-20" aria-label="Broadcast members">
                {page === null ? (
                    <li className="py-4 text-center text-sm text-muted-foreground">
                        {error === null
                            ? 'Loading members…'
                            : 'Use Refresh to try again.'}
                    </li>
                ) : page.members.length === 0 ? (
                    <li className="py-4 text-center text-sm text-muted-foreground">
                        No matching members.
                    </li>
                ) : (
                    page.members.map((member) => (
                        <li
                            key={member.user.id}
                            className="flex min-h-16 items-center gap-2 py-2"
                            data-test="broadcast-member"
                        >
                            <UserAvatar
                                user={member.user}
                                className="size-8 shrink-0"
                            />
                            <div className="min-w-0 flex-1">
                                <p
                                    className="truncate text-sm font-medium"
                                    title={member.user.name}
                                >
                                    {member.user.name}
                                </p>
                                <p
                                    className="truncate font-mono text-xs text-muted-foreground"
                                    title={`@${member.user.username}`}
                                >
                                    @{member.user.username} ·{' '}
                                    {member.is_banned ? 'banned' : member.role}
                                </p>
                            </div>
                            {member.role !== 'owner' && (
                                <div className="flex shrink-0 gap-1">
                                    {member.is_banned ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={isBusy}
                                            onClick={() =>
                                                setConfirmation({
                                                    member,
                                                    action: 'unban',
                                                })
                                            }
                                        >
                                            Unban
                                        </Button>
                                    ) : (
                                        <>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={isBusy}
                                                onClick={() =>
                                                    setConfirmation({
                                                        member,
                                                        action: 'kick',
                                                    })
                                                }
                                            >
                                                Kick
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={isBusy}
                                                onClick={() =>
                                                    setConfirmation({
                                                        member,
                                                        action: 'ban',
                                                    })
                                                }
                                            >
                                                Ban
                                            </Button>
                                        </>
                                    )}
                                </div>
                            )}
                        </li>
                    ))
                )}
            </ul>
            {page?.next_cursor !== null && page !== null && (
                <Button
                    variant="ghost"
                    className="w-full"
                    disabled={isLoadingMore}
                    onClick={() => void loadMore()}
                >
                    {isLoadingMore ? 'Loading…' : 'More members'}
                </Button>
            )}
            <Dialog
                open={confirmation !== null}
                onOpenChange={(open) => {
                    if (!open && !isBusy) setConfirmation(null);
                }}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                    <DialogTitle>
                        {confirmation?.action === 'unban'
                            ? 'Unban'
                            : confirmation?.action === 'ban'
                              ? 'Ban'
                              : 'Kick'}{' '}
                        @{confirmation?.member.user.username}?
                    </DialogTitle>
                    <DialogDescription>
                        {confirmation?.action === 'unban'
                            ? 'They can subscribe again, or accept a new invitation. This does not restore their subscription or publishing role.'
                            : confirmation?.action === 'ban'
                              ? 'They immediately lose access and cannot rejoin until you unban them. Their original history cutoff stays preserved.'
                              : 'They immediately lose access and any publishing role. They can rejoin an open channel, or accept a new invitation. Their original history cutoff stays preserved.'}
                    </DialogDescription>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={isBusy}
                            onClick={() => setConfirmation(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant={
                                confirmation?.action === 'unban'
                                    ? 'default'
                                    : 'destructive'
                            }
                            disabled={isBusy}
                            className="min-w-20"
                            onClick={() => void moderate()}
                        >
                            {confirmation?.action === 'unban'
                                ? 'Unban'
                                : confirmation?.action === 'ban'
                                  ? 'Ban'
                                  : 'Kick'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
