import { Link } from '@inertiajs/react';
import { Globe, Lock, Radio, UserPlus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { BroadcastMembers } from '@/components/chat/broadcast-members';
import { ConversationActions } from '@/components/chat/conversation-actions';
import { SegmentedControl } from '@/components/segmented-control';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useChat } from '@/hooks/use-chat';
import {
    ChatRequestError,
    createBroadcast,
    inviteToBroadcast,
    leaveBroadcast,
    searchBroadcasts,
    setBroadcastPublisher,
    subscribeBroadcast,
    targetKey,
    updateBroadcast,
} from '@/lib/chat';
import { cn, laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type {
    BroadcastRef,
    BroadcastSummary,
    BroadcastVisibility,
    User,
} from '@/types';

const searchDebounce = 150;

function errorOf(error: unknown): string {
    return error instanceof ChatRequestError
        ? error.firstError()
        : 'Something went wrong. Try again.';
}

/**
 * A broadcast's mark, where a person would have their avatar: waves going out.
 */
export function BroadcastTile({
    isCompact = false,
    className,
}: {
    isCompact?: boolean;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'grid shrink-0 place-items-center rounded-lg bg-primary-soft text-primary',
                isCompact ? 'size-8' : 'size-10',
                className,
            )}
        >
            <Radio className={cn(isCompact ? 'size-4' : 'size-[18px]')} />
        </span>
    );
}

export function broadcastTileLabel(count: number): string {
    return `${count} ${count === 1 ? 'subscriber' : 'subscribers'}`;
}

/**
 * A broadcast's header: its mark and name, which open its people and settings, and its actions.
 */
export function BroadcastHeader({
    broadcast,
    user,
    isCompact = false,
    before,
    after,
    onLeft,
}: {
    broadcast: BroadcastRef;
    user: User;
    isCompact?: boolean;
    before?: ReactNode;
    after?: ReactNode;
    onLeft: () => void;
}) {
    const { threads } = useChat();
    const [isShowingPeople, setIsShowingPeople] = useState(false);
    const thread = threads[targetKey({ kind: 'broadcast', broadcast })];
    const current =
        thread?.target.kind === 'broadcast'
            ? thread.target.broadcast
            : broadcast;
    const count = thread?.subscriberCount ?? 0;

    return (
        <>
            {before}
            <button
                type="button"
                onClick={() => setIsShowingPeople(true)}
                className="flex min-w-0 items-center gap-3 rounded-lg text-left"
                aria-label={`About ${current.name}`}
                data-test="broadcast-header"
            >
                <BroadcastTile isCompact={isCompact} />
                <span className="min-w-0">
                    <span
                        className={cn(
                            'block truncate font-semibold hover:underline',
                            isCompact ? 'text-sm' : 'text-[15px]',
                        )}
                        title={current.name}
                    >
                        {current.name}
                    </span>
                    {!isCompact && (
                        <span className="block truncate font-mono text-xs text-muted-foreground tabular-nums">
                            broadcast · {count || '–'} subscribers
                        </span>
                    )}
                </span>
            </button>
            <ConversationActions
                target={{ kind: 'broadcast', broadcast: current }}
                title={current.name}
                user={user}
                isArchived={thread?.is_archived ?? false}
                canLeave={(thread?.role ?? null) !== 'owner'}
                onLeave={onLeft}
                className={cn('ml-auto', { 'size-9': !isCompact })}
            />
            {after}
            <BroadcastPeopleDialog
                broadcast={current}
                user={user}
                open={isShowingPeople}
                onOpenChange={setIsShowingPeople}
                onLeft={onLeft}
            />
        </>
    );
}

/**
 * A broadcast's people: its publishers for everyone, and for its owner the name, the visibility, invitations by
 * handle, and who may post. Anyone but the owner leaves from here.
 */
function BroadcastPeopleDialog({
    broadcast,
    user,
    open,
    onOpenChange,
    onLeft,
}: {
    broadcast: BroadcastRef;
    user: User;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onLeft: () => void;
}) {
    const { threads } = useChat();
    const thread = threads[targetKey({ kind: 'broadcast', broadcast })];
    const role = thread?.role ?? null;
    const isOwner = role === 'owner';
    const [name, setName] = useState(broadcast.name);
    const [visibility, setVisibility] = useState<BroadcastVisibility>(
        broadcast.visibility ?? 'invite',
    );
    const [invitee, setInvitee] = useState('');
    const [publisher, setPublisher] = useState('');
    const [isBusy, setIsBusy] = useState(false);
    const [isConfirmingLeave, setIsConfirmingLeave] = useState(false);

    const run = async (change: () => Promise<void>, done?: () => void) => {
        setIsBusy(true);

        try {
            await change();
            done?.();
        } catch (error) {
            toast.error(errorOf(error));
        } finally {
            setIsBusy(false);
        }
    };

    const rename = (event: FormEvent) => {
        event.preventDefault();
        void run(() => updateBroadcast(broadcast, name.trim(), null));
    };

    const changeVisibility = (next: BroadcastVisibility) => {
        setVisibility(next);
        void run(() => updateBroadcast(broadcast, null, next));
    };

    const leave = () =>
        void run(
            () => leaveBroadcast(broadcast),
            () => {
                onOpenChange(false);
                onLeft();
            },
        );

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                setIsConfirmingLeave(false);
                setName(broadcast.name);
                setVisibility(broadcast.visibility ?? 'invite');
                setInvitee('');
                setPublisher('');
                onOpenChange(isOpen);
            }}
        >
            <DialogContent
                className="flex max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-md"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    (event.currentTarget as HTMLElement).focus();
                }}
                data-test="broadcast-people"
            >
                <div className="space-y-3 border-b px-5 pt-5 pb-4">
                    <div className="flex min-w-0 items-center gap-3">
                        <BroadcastTile />
                        <div className="min-w-0 space-y-0.5">
                            <DialogTitle
                                className="truncate pr-6"
                                title={broadcast.name}
                            >
                                {broadcast.name}
                            </DialogTitle>
                            <DialogDescription className="font-mono text-xs tabular-nums">
                                {broadcastTileLabel(
                                    thread?.subscriberCount ?? 0,
                                )}
                            </DialogDescription>
                        </div>
                    </div>
                    {isOwner && (
                        <>
                            <form onSubmit={rename} className="flex gap-2">
                                <label
                                    className="sr-only"
                                    htmlFor="broadcast-name"
                                >
                                    Broadcast name
                                </label>
                                <input
                                    id="broadcast-name"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    maxLength={60}
                                    placeholder="Name the broadcast"
                                    className="h-9 min-w-0 flex-1 rounded-md border bg-card px-3 text-base outline-none focus-visible:border-primary md:text-sm"
                                    data-test="broadcast-name"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="min-w-20"
                                    disabled={
                                        isBusy ||
                                        name.trim() === broadcast.name ||
                                        name.trim() === ''
                                    }
                                >
                                    Rename
                                </Button>
                            </form>
                            <SegmentedControl
                                label="Visibility"
                                name="broadcast-visibility"
                                value={visibility}
                                onChange={changeVisibility}
                                className="w-full [&>button]:flex-1"
                                options={[
                                    {
                                        value: 'public' as BroadcastVisibility,
                                        icon: Globe,
                                        label: 'Public',
                                    },
                                    {
                                        value: 'invite' as BroadcastVisibility,
                                        icon: Lock,
                                        label: 'Invite-only',
                                    },
                                ]}
                            />
                        </>
                    )}
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain py-2">
                    <h3 className="px-5 pt-1 pb-1 font-mono text-xs text-muted-foreground">
                        Publishers
                    </h3>
                    <ul>
                        {(thread?.publishers ?? []).map((person) => (
                            <PublisherRow
                                key={person.id}
                                person={person}
                                broadcast={broadcast}
                                user={user}
                                canManage={
                                    isOwner && person.id !== user.id && !isBusy
                                }
                            />
                        ))}
                    </ul>
                    {isOwner && (
                        <>
                            <h3 className="px-5 pt-4 pb-1 font-mono text-xs text-muted-foreground">
                                People
                            </h3>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    void run(
                                        () =>
                                            inviteToBroadcast(
                                                broadcast,
                                                invitee
                                                    .trim()
                                                    .replace(/^@/u, ''),
                                            ),
                                        () => setInvitee(''),
                                    );
                                }}
                                className="flex items-center gap-2 px-5 py-2"
                            >
                                <label
                                    className="sr-only"
                                    htmlFor="broadcast-invitee"
                                >
                                    Invite by handle
                                </label>
                                <input
                                    id="broadcast-invitee"
                                    value={invitee}
                                    onChange={(event) =>
                                        setInvitee(event.target.value)
                                    }
                                    maxLength={39}
                                    placeholder="Invite by @handle"
                                    autoComplete="off"
                                    spellCheck={false}
                                    className="h-9 min-w-0 flex-1 rounded-md border bg-card px-3 font-mono text-base outline-none focus-visible:border-primary md:text-sm"
                                    data-test="broadcast-invitee"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="min-w-20"
                                    disabled={isBusy || invitee.trim() === ''}
                                >
                                    <UserPlus className="size-4" />
                                    Invite
                                </Button>
                            </form>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    void run(
                                        () =>
                                            setBroadcastPublisher(
                                                broadcast,
                                                publisher
                                                    .trim()
                                                    .replace(/^@/u, ''),
                                                true,
                                            ),
                                        () => setPublisher(''),
                                    );
                                }}
                                className="flex items-center gap-2 px-5 py-2"
                            >
                                <label
                                    className="sr-only"
                                    htmlFor="broadcast-publisher"
                                >
                                    Make a subscriber a publisher
                                </label>
                                <input
                                    id="broadcast-publisher"
                                    value={publisher}
                                    onChange={(event) =>
                                        setPublisher(event.target.value)
                                    }
                                    maxLength={39}
                                    placeholder="Promote @handle to publisher"
                                    autoComplete="off"
                                    spellCheck={false}
                                    className="h-9 min-w-0 flex-1 rounded-md border bg-card px-3 font-mono text-base outline-none focus-visible:border-primary md:text-sm"
                                    data-test="broadcast-publisher"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="min-w-20"
                                    disabled={isBusy || publisher.trim() === ''}
                                >
                                    Promote
                                </Button>
                            </form>
                            {open && <BroadcastMembers broadcast={broadcast} />}
                        </>
                    )}
                </div>

                <div className="flex items-center gap-2 border-t px-5 py-3">
                    <p className="min-w-0 flex-1 font-mono text-xs text-muted-foreground">
                        subscribers read. publishers push.
                    </p>
                    {role !== null && role !== 'owner' && (
                        <Button
                            variant={
                                isConfirmingLeave ? 'destructive' : 'ghost'
                            }
                            className="min-w-28"
                            onClick={() =>
                                isConfirmingLeave
                                    ? leave()
                                    : setIsConfirmingLeave(true)
                            }
                            data-test="broadcast-leave"
                        >
                            {isConfirmingLeave
                                ? 'Leave for good'
                                : 'Leave broadcast'}
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function PublisherRow({
    person,
    broadcast,
    user,
    canManage,
}: {
    person: User;
    broadcast: BroadcastRef;
    user: User;
    canManage: boolean;
}) {
    return (
        <li className="flex items-center gap-3 px-5 py-2">
            <Link href={show(person)} className="shrink-0">
                <UserAvatar
                    user={person}
                    className="size-9 ring-2 ring-(--lane)"
                    style={
                        {
                            '--lane': laneColor(person.lane),
                        } as React.CSSProperties
                    }
                />
            </Link>
            <span className="min-w-0 flex-1">
                <Link
                    href={show(person)}
                    className="block truncate text-sm font-semibold hover:underline"
                    title={person.name}
                >
                    {person.name}
                    {person.id === user.id && (
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            (you)
                        </span>
                    )}
                </Link>
                <span className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                    <span className="truncate">@{person.username}</span>
                </span>
            </span>
            {canManage ? (
                <Button
                    variant="outline"
                    className="h-7 shrink-0 px-2.5 text-xs"
                    onClick={() =>
                        void setBroadcastPublisher(
                            broadcast,
                            person.username,
                            false,
                        ).catch((error: unknown) => toast.error(errorOf(error)))
                    }
                    data-test="broadcast-demote"
                >
                    Demote
                </Button>
            ) : (
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2 font-mono text-[11px] leading-5',
                        person.id === user.id
                            ? 'bg-primary-soft text-primary'
                            : 'bg-muted text-muted-foreground',
                    )}
                    data-test="broadcast-publisher-role"
                >
                    publisher
                </span>
            )}
        </li>
    );
}

type Found = {
    term: string;
    broadcasts: BroadcastSummary[];
    has_more: boolean;
};

/**
 * The broadcast directory: every public one and the viewer's own, searched by name or owner, with the form that
 * starts a new one. Opening a row leaves the choice to the broadcast's own page.
 */
export function BroadcastDirectoryDialog({
    open,
    onOpenChange,
    onOpen,
    initialQuery = '',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onOpen: (broadcast: { id: string }) => void;
    initialQuery?: string;
}) {
    const [query, setQuery] = useState(initialQuery);
    const [found, setFound] = useState<Found | null>(null);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [name, setName] = useState('');
    const [visibility, setVisibility] = useState<BroadcastVisibility>('public');
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const isCreated = useRef(false);
    const term = query.trim().replace(/^@/u, '');

    const load = (
        signal: AbortSignal,
        before?: string,
        soFar?: Found,
    ): Promise<void> => {
        setSearchError(null);

        return searchBroadcasts(term, signal, before)
            .then((page) =>
                setFound({
                    term,
                    broadcasts: [
                        ...(soFar?.term === term ? soFar.broadcasts : []),
                        ...page.broadcasts,
                    ],
                    has_more: page.has_more,
                }),
            )
            .catch((failure: unknown) => {
                if (!signal.aborted) {
                    setSearchError(errorOf(failure));
                }
            });
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(
            () => load(controller.signal),
            term === '' ? 0 : searchDebounce,
        );

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
        // Each keystroke searches the directory again, so the effect depends on the term alone.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term, open]);

    const reset = () => {
        setQuery(initialQuery);
        setFound(null);
        setSearchError(null);
        setName('');
        setVisibility('public');
        setError(null);
    };

    const loadMore = () => {
        if (found === null || isLoadingMore) {
            return;
        }

        setIsLoadingMore(true);
        const controller = new AbortController();
        void load(
            controller.signal,
            found.broadcasts.at(-1)?.id,
            found,
        ).finally(() => setIsLoadingMore(false));
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (name.trim() === '') {
            return;
        }

        setIsSaving(true);

        try {
            const id = await createBroadcast(name.trim(), visibility);

            isCreated.current = true;
            reset();
            onOpenChange(false);
            onOpen({ id });
        } catch (failure) {
            setError(errorOf(failure));
        } finally {
            setIsSaving(false);
        }
    };

    const results = found !== null && found.term === term ? found : null;

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    reset();
                }

                onOpenChange(isOpen);
            }}
        >
            <DialogContent
                className="flex max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-md"
                onCloseAutoFocus={(event) => {
                    // The new broadcast's composer takes focus, so it isn't handed back to the button that opened this.
                    if (isCreated.current) {
                        event.preventDefault();
                        isCreated.current = false;
                    }
                }}
                data-test="broadcast-directory"
            >
                <div className="space-y-3 border-b px-5 pt-5 pb-4">
                    <div className="space-y-1">
                        <DialogTitle>Broadcasts</DialogTitle>
                        <DialogDescription>
                            Public channels anyone can follow, plus the ones
                            you&apos;re in or invited to.
                        </DialogDescription>
                    </div>
                    <label className="relative flex items-center">
                        <span className="sr-only">Search broadcasts</span>
                        <Radio
                            aria-hidden
                            className="pointer-events-none absolute left-3 size-4 text-muted-foreground"
                        />
                        <input
                            autoFocus
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            maxLength={100}
                            placeholder="Search by name or @owner"
                            autoComplete="off"
                            spellCheck={false}
                            className="h-9 w-full rounded-md border bg-card pr-3 pl-9 text-base outline-none focus-visible:border-primary md:text-sm"
                            data-test="broadcast-search"
                        />
                    </label>
                </div>

                <ul
                    aria-label="Broadcasts"
                    className="min-h-40 flex-1 overflow-y-auto overscroll-contain py-2"
                >
                    {searchError !== null ? (
                        <li className="space-y-2 px-5 py-8 text-center">
                            <p
                                role="alert"
                                className="font-mono text-xs text-destructive"
                            >
                                {searchError}
                            </p>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    void load(new AbortController().signal)
                                }
                            >
                                Retry search
                            </Button>
                        </li>
                    ) : results === null ? (
                        <li className="px-5 py-8 text-center font-mono text-[13px] text-muted-foreground">
                            {term === '' ? 'loading…' : 'searching…'}
                        </li>
                    ) : results.broadcasts.length === 0 ? (
                        <li
                            className="truncate px-5 py-8 text-center font-mono text-[13px] text-muted-foreground"
                            title={`fatal: nothing matches '${term}'`}
                        >
                            fatal: nothing matches &apos;{term}&apos;
                        </li>
                    ) : (
                        results.broadcasts.map((broadcast) => (
                            <li key={broadcast.id}>
                                <DirectoryRow
                                    broadcast={broadcast}
                                    onOpen={() => onOpen(broadcast)}
                                />
                            </li>
                        ))
                    )}
                    {results !== null && results.has_more && (
                        <li className="flex h-10 items-center justify-center">
                            {isLoadingMore ? (
                                <Spinner className="size-4 text-muted-foreground" />
                            ) : (
                                <Button
                                    variant="ghost"
                                    className="h-8 text-[13px]"
                                    onClick={loadMore}
                                    data-test="broadcast-load-more"
                                >
                                    More broadcasts
                                </Button>
                            )}
                        </li>
                    )}
                </ul>

                <form
                    onSubmit={(event) => void submit(event)}
                    className="space-y-3 border-t px-5 py-3"
                >
                    <div className="flex items-center gap-2">
                        <label className="sr-only" htmlFor="new-broadcast-name">
                            New broadcast name
                        </label>
                        <input
                            id="new-broadcast-name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            maxLength={60}
                            placeholder="Start a broadcast"
                            className="h-9 min-w-0 flex-1 rounded-md border bg-card px-3 text-base outline-none focus-visible:border-primary md:text-sm"
                            data-test="new-broadcast-name"
                        />
                        <Button
                            type="submit"
                            className="min-w-20"
                            disabled={isSaving || name.trim() === ''}
                            data-test="new-broadcast-submit"
                        >
                            Start
                        </Button>
                    </div>
                    <SegmentedControl
                        label="Visibility"
                        name="new-broadcast-visibility"
                        value={visibility}
                        onChange={setVisibility}
                        className="w-full [&>button]:flex-1"
                        options={[
                            {
                                value: 'public' as BroadcastVisibility,
                                icon: Globe,
                                label: 'Public',
                            },
                            {
                                value: 'invite' as BroadcastVisibility,
                                icon: Lock,
                                label: 'Invite-only',
                            },
                        ]}
                    />
                    <div className="flex items-center gap-3">
                        <p
                            role="alert"
                            className="min-w-0 flex-1 truncate font-mono text-xs text-destructive"
                            title={error ?? undefined}
                        >
                            {error}
                        </p>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DirectoryRow({
    broadcast,
    onOpen,
}: {
    broadcast: BroadcastSummary;
    onOpen: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onOpen}
            className="flex w-full items-center gap-3 px-5 py-2 text-left transition-colors hover:bg-accent focus-visible:-outline-offset-2"
            data-test="broadcast-directory-row"
        >
            <BroadcastTile />
            <span className="min-w-0 flex-1">
                <span className="flex min-w-0 items-baseline gap-1.5">
                    <span
                        className="truncate text-sm font-semibold"
                        title={broadcast.name}
                    >
                        {broadcast.name}
                    </span>
                    {broadcast.visibility === 'invite' && (
                        <span className="flex shrink-0 items-center gap-0.5 font-mono text-[11px] text-muted-foreground">
                            <Lock className="size-3" />
                            invite-only
                        </span>
                    )}
                </span>
                <span className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                    <span className="truncate">
                        @{broadcast.owner.username}
                    </span>
                    <span className="shrink-0 tabular-nums">
                        · {broadcastTileLabel(broadcast.subscriber_count)}
                    </span>
                </span>
            </span>
            <span
                className={cn(
                    'shrink-0 rounded-full px-2 font-mono text-[11px] leading-5',
                    broadcast.membership === 'invited'
                        ? 'bg-primary-soft text-primary'
                        : 'bg-muted text-muted-foreground',
                )}
                data-test="broadcast-membership"
            >
                {broadcast.membership}
            </span>
        </button>
    );
}

/**
 * What someone who is not in the broadcast sees of it: who runs it, how many follow, and the way in. History starts
 * at the latest message once they subscribe, and the server keeps that cutoff on rejoining.
 */
export function BroadcastLanding({
    broadcast,
    onSubscribed,
}: {
    broadcast: BroadcastSummary;
    onSubscribed: () => void;
}) {
    const [isSubscribing, setIsSubscribing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const isInvited = broadcast.membership === 'invited';

    const subscribe = async () => {
        setIsSubscribing(true);

        try {
            await subscribeBroadcast(broadcast);
            onSubscribed();
        } catch (failure) {
            setError(errorOf(failure));
        } finally {
            setIsSubscribing(false);
        }
    };

    return (
        <section
            aria-label={`About ${broadcast.name}`}
            className="flex min-h-0 min-w-0 flex-1 flex-col"
            data-test="broadcast-landing"
        >
            <div className="flex flex-1 flex-col items-center justify-center gap-4 px-8 py-10 text-center">
                <BroadcastTile className="size-14 [&>svg]:size-6" />
                <div className="min-w-0">
                    <h2
                        className="text-lg font-semibold tracking-tight"
                        title={broadcast.name}
                    >
                        {broadcast.name}
                    </h2>
                    <p className="mt-1 flex min-w-0 items-center justify-center gap-1.5 font-mono text-xs text-muted-foreground">
                        {broadcast.visibility === 'invite' && (
                            <>
                                <Lock className="size-3 shrink-0" />
                                invite-only ·
                            </>
                        )}
                        <Link
                            href={show(broadcast.owner)}
                            className="truncate hover:underline"
                            title={broadcast.owner.name}
                        >
                            @{broadcast.owner.username}
                        </Link>
                        <span className="shrink-0 tabular-nums">
                            · {broadcastTileLabel(broadcast.subscriber_count)}
                        </span>
                    </p>
                </div>
                <p className="max-w-72 text-sm text-muted-foreground">
                    {isInvited
                        ? `You're invited. Accepting starts your history at the latest push.`
                        : 'The owner and its publishers push; everyone else reads and reacts.'}
                </p>
                <Button
                    className="min-w-28"
                    disabled={isSubscribing}
                    onClick={() => void subscribe()}
                    data-test="broadcast-subscribe"
                >
                    {isInvited ? 'Accept invite' : 'Subscribe'}
                </Button>
                <p
                    role="alert"
                    className="min-h-4 font-mono text-xs text-destructive"
                    title={error ?? undefined}
                >
                    {error}
                </p>
            </div>
        </section>
    );
}
