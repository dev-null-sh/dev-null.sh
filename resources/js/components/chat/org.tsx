import { Link } from '@inertiajs/react';
import { Check, Ellipsis, Search, UserPlus, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConversationActions } from '@/components/chat/conversation-actions';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useChat } from '@/hooks/use-chat';
import {
    addOrgMembers,
    ChatRequestError,
    createOrg,
    removeOrgMember,
    renameOrg,
    searchCandidates,
    setOrgMemberRole,
} from '@/lib/chat';
import type { Candidate } from '@/lib/chat';
import { cn, laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Message, OrgMember, OrgRef, OrgRole, User } from '@/types';

export const maxMembers = 100;

const searchDebounce = 150;

/**
 * An org goes by its name, or by its other members' names when it has none.
 */
export function orgTitle(
    org: { name: string | null },
    members: { user: User }[] | null,
    user: User,
): string {
    if (org.name) {
        return org.name;
    }

    const others = (members ?? []).filter(
        (member) => member.user.id !== user.id,
    );

    return others.length === 0
        ? 'Just you'
        : others.map((member) => member.user.name.split(' ')[0]).join(', ');
}

export function MemberStack({
    users,
    max = 3,
    className,
    avatarClassName = 'size-7',
}: {
    users: User[];
    max?: number;
    className?: string;
    avatarClassName?: string;
}) {
    const shown = users.slice(0, max);
    const rest = users.length - shown.length;

    return (
        <span className={cn('flex shrink-0 items-center', className)}>
            {shown.map((member) => (
                <UserAvatar
                    key={member.id}
                    user={member}
                    className={cn(
                        'ring-2 ring-card not-first:-ml-2',
                        avatarClassName,
                    )}
                />
            ))}
            {rest > 0 && (
                <span
                    className={cn(
                        '-ml-2 grid place-items-center rounded-full bg-muted font-mono text-[11px] text-muted-foreground tabular-nums ring-2 ring-card',
                        avatarClassName,
                    )}
                >
                    +{rest}
                </span>
            )}
        </span>
    );
}

function handleOf(person: User | null | undefined, user: User): string {
    if (person === null || person === undefined) {
        return 'someone';
    }

    return person.id === user.id ? 'you' : `@${person.username}`;
}

/**
 * What a logged org change says, from the reader's point of view.
 */
export function eventText(message: Message, user: User): string {
    const actor = handleOf(message.user, user);
    const subject = handleOf(message.subject, user);

    switch (message.event) {
        case 'created':
            return message.body
                ? `${actor} created ${message.body}`
                : `${actor} created the org`;
        case 'added':
            return `${actor} added ${subject}`;
        case 'removed':
            return `${actor} removed ${subject}`;
        case 'left':
            return `${actor} left`;
        case 'renamed':
            return message.body
                ? `${actor} renamed the org to ${message.body}`
                : `${actor} cleared the org's name`;
        case 'promoted':
            return `${actor} made ${subject} a maintainer`;
        case 'demoted':
            return `${actor} made ${subject} a contributor`;
        case 'inherited':
            return `${actor} inherited maintainer`;
        default:
            return message.body;
    }
}

export function EventLine({ message, user }: { message: Message; user: User }) {
    const text = eventText(message, user);

    return (
        <li className="my-2 flex justify-center">
            <span
                className="flex max-w-full min-w-0 items-center gap-1.5 font-mono text-[11px] text-muted-foreground"
                title={text}
                data-test="org-event"
            >
                <span aria-hidden className="shrink-0">
                    *
                </span>
                <span className="truncate">{text}</span>
            </span>
        </li>
    );
}

/**
 * Who is typing in an org, by handle, without running past one line.
 */
export function typingText(usernames: string[]): string {
    if (usernames.length === 1) {
        return `@${usernames[0]} is typing…`;
    }

    if (usernames.length === 2) {
        return `@${usernames[0]} and @${usernames[1]} are typing…`;
    }

    return `${usernames.length} people are typing…`;
}

/**
 * The org's header: its members' avatars, which open the members dialog, its name, and its actions.
 */
export function OrgHeader({
    org,
    user,
    isCompact = false,
    before,
    after,
    onLeft,
}: {
    org: OrgRef;
    user: User;
    isCompact?: boolean;
    before?: ReactNode;
    after?: ReactNode;
    onLeft: () => void;
}) {
    const { threads } = useChat();
    const [isShowingMembers, setIsShowingMembers] = useState(false);
    const thread = threads[`org:${org.id}`];
    const current = thread?.target.kind === 'org' ? thread.target.org : org;
    const members = thread?.members ?? [];
    const title = orgTitle(current, members, user);

    return (
        <>
            {before}
            <button
                type="button"
                onClick={() => setIsShowingMembers(true)}
                className="flex min-w-0 items-center gap-3 rounded-lg text-left"
                aria-label={`Members of ${title}`}
                data-test="org-member-stack"
            >
                <MemberStack
                    users={members
                        .map((member) => member.user)
                        .toSorted(
                            (a, b) =>
                                Number(a.id === user.id) -
                                Number(b.id === user.id),
                        )}
                    max={isCompact ? 2 : 4}
                    avatarClassName={isCompact ? 'size-6' : 'size-8'}
                />
                <span className="min-w-0">
                    <span
                        className={cn(
                            'block truncate font-semibold hover:underline',
                            isCompact ? 'text-sm' : 'text-[15px]',
                        )}
                        title={title}
                    >
                        {title}
                    </span>
                    {!isCompact && (
                        <span className="block truncate font-mono text-xs text-muted-foreground tabular-nums">
                            org · {members.length || '–'} members
                        </span>
                    )}
                </span>
            </button>
            <ConversationActions
                target={{ kind: 'org', org: current }}
                title={title}
                user={user}
                isArchived={thread?.is_archived ?? false}
                onLeave={onLeft}
                className={cn('ml-auto', { 'size-9': !isCompact })}
            />
            {after}
            <OrgMembersDialog
                org={current}
                members={members}
                role={thread?.role ?? null}
                user={user}
                open={isShowingMembers}
                onOpenChange={setIsShowingMembers}
                onLeft={onLeft}
            />
        </>
    );
}

function errorOf(error: unknown): string {
    return error instanceof ChatRequestError
        ? error.firstError()
        : 'Something went wrong. Try again.';
}

/**
 * Everyone in the org with their role. Maintainers rename the org, add and remove people, and promote or demote
 * them; anyone can leave.
 */
export function OrgMembersDialog({
    org,
    members,
    role,
    user,
    open,
    onOpenChange,
    onLeft,
}: {
    org: OrgRef;
    members: OrgMember[];
    role: OrgRole | null;
    user: User;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onLeft: () => void;
}) {
    const isMaintainer = role === 'maintainer';
    const [name, setName] = useState(org.name ?? '');
    const [isSaving, setIsSaving] = useState(false);
    const [isAdding, setIsAdding] = useState(false);
    const [isConfirmingLeave, setIsConfirmingLeave] = useState(false);
    const maintainers = members.filter(
        (member) => member.role === 'maintainer',
    ).length;

    const rename = async (event: FormEvent) => {
        event.preventDefault();
        setIsSaving(true);

        try {
            await renameOrg(org, name);
        } catch (error) {
            toast.error(errorOf(error));
        } finally {
            setIsSaving(false);
        }
    };

    const leave = async () => {
        try {
            await removeOrgMember(org, user, true);
            onOpenChange(false);
            onLeft();
        } catch (error) {
            toast.error(errorOf(error));
        }
    };

    return (
        <>
            <Dialog
                open={open && !isAdding}
                onOpenChange={(isOpen) => {
                    setIsConfirmingLeave(false);
                    setName(org.name ?? '');
                    onOpenChange(isOpen);
                }}
            >
                <DialogContent
                    className="flex max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-md"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        (event.currentTarget as HTMLElement).focus();
                    }}
                    data-test="org-members"
                >
                    <div className="space-y-1 border-b px-5 pt-5 pb-4">
                        <DialogTitle
                            className="truncate pr-6"
                            title={org.name ?? undefined}
                        >
                            {org.name ?? 'Org'}
                        </DialogTitle>
                        <DialogDescription className="font-mono text-xs tabular-nums">
                            {members.length}/{maxMembers} members
                        </DialogDescription>
                        {isMaintainer && (
                            <form
                                onSubmit={(event) => void rename(event)}
                                className="flex gap-2 pt-2"
                            >
                                <label
                                    className="sr-only"
                                    htmlFor={`org-name-${org.id}`}
                                >
                                    Org name
                                </label>
                                <input
                                    id={`org-name-${org.id}`}
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    maxLength={60}
                                    placeholder="Name the org"
                                    className="h-9 min-w-0 flex-1 rounded-md border bg-card px-3 text-base outline-none focus-visible:border-primary md:text-sm"
                                    data-test="org-name"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="min-w-20"
                                    disabled={
                                        isSaving ||
                                        name.trim() === (org.name ?? '')
                                    }
                                >
                                    Rename
                                </Button>
                            </form>
                        )}
                    </div>

                    <ul className="min-h-0 flex-1 overflow-y-auto overscroll-contain py-2">
                        {members.map((member) => (
                            <MemberRow
                                key={member.user.id}
                                org={org}
                                member={member}
                                user={user}
                                canManage={
                                    isMaintainer && member.user.id !== user.id
                                }
                                isLastMaintainer={
                                    member.role === 'maintainer' &&
                                    maintainers === 1
                                }
                            />
                        ))}
                    </ul>

                    <div className="flex items-center gap-2 border-t px-5 py-3">
                        {isMaintainer && (
                            <Button
                                variant="outline"
                                disabled={members.length >= maxMembers}
                                title={
                                    members.length >= maxMembers
                                        ? `The org is full (${maxMembers})`
                                        : undefined
                                }
                                onClick={() => setIsAdding(true)}
                                data-test="org-add-people"
                            >
                                <UserPlus className="size-4" />
                                Add people
                            </Button>
                        )}
                        <Button
                            variant={
                                isConfirmingLeave ? 'destructive' : 'ghost'
                            }
                            className="ml-auto min-w-28"
                            onClick={() =>
                                isConfirmingLeave
                                    ? void leave()
                                    : setIsConfirmingLeave(true)
                            }
                            data-test="org-leave"
                        >
                            {isConfirmingLeave ? 'Leave for good' : 'Leave org'}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            {isMaintainer && (
                <OrgPeopleDialog
                    mode={{ kind: 'add', org, members }}
                    open={open && isAdding}
                    onOpenChange={setIsAdding}
                    onDone={() => setIsAdding(false)}
                />
            )}
        </>
    );
}

function MemberRow({
    org,
    member,
    user,
    canManage,
    isLastMaintainer,
}: {
    org: OrgRef;
    member: OrgMember;
    user: User;
    canManage: boolean;
    isLastMaintainer: boolean;
}) {
    const [isBusy, setIsBusy] = useState(false);

    const run = async (change: () => Promise<void>) => {
        setIsBusy(true);

        try {
            await change();
        } catch (error) {
            toast.error(errorOf(error));
        } finally {
            setIsBusy(false);
        }
    };

    return (
        <li className="flex items-center gap-3 px-5 py-2">
            <Link href={show(member.user)} className="shrink-0">
                <UserAvatar
                    user={member.user}
                    className="size-9 ring-2 ring-(--lane)"
                    style={
                        {
                            '--lane': laneColor(member.user.lane),
                        } as React.CSSProperties
                    }
                />
            </Link>
            <span className="min-w-0 flex-1">
                <Link
                    href={show(member.user)}
                    className="block truncate text-sm font-semibold hover:underline"
                    title={member.user.name}
                >
                    {member.user.name}
                    {member.user.id === user.id && (
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            (you)
                        </span>
                    )}
                </Link>
                <span className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                    <span className="truncate">@{member.user.username}</span>
                </span>
            </span>
            <span
                className={cn(
                    'shrink-0 rounded-sm px-2 font-mono text-[11px] leading-5',
                    member.role === 'maintainer'
                        ? 'bg-primary-soft text-primary'
                        : 'bg-muted text-muted-foreground',
                )}
                data-test="org-member-role"
            >
                {member.role ?? 'contributor'}
            </span>
            <span className="grid size-8 shrink-0 place-items-center">
                {canManage &&
                    (isBusy ? (
                        <Spinner className="size-4 text-muted-foreground" />
                    ) : (
                        <DropdownMenu modal={false}>
                            <DropdownMenuTrigger
                                aria-label={`Manage @${member.user.username}`}
                                className="grid size-8 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground data-[state=open]:bg-accent"
                                data-test="org-member-actions"
                            >
                                <Ellipsis className="size-4" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                {member.role === 'maintainer' ? (
                                    <DropdownMenuItem
                                        disabled={isLastMaintainer}
                                        onSelect={() =>
                                            void run(() =>
                                                setOrgMemberRole(
                                                    org,
                                                    member.user,
                                                    'contributor',
                                                ),
                                            )
                                        }
                                    >
                                        Make contributor
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            void run(() =>
                                                setOrgMemberRole(
                                                    org,
                                                    member.user,
                                                    'maintainer',
                                                ),
                                            )
                                        }
                                    >
                                        Make maintainer
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() =>
                                        void run(() =>
                                            removeOrgMember(
                                                org,
                                                member.user,
                                                false,
                                            ),
                                        )
                                    }
                                    data-test="org-member-remove"
                                >
                                    Remove from org
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ))}
            </span>
        </li>
    );
}

type PeopleMode =
    | { kind: 'create' }
    | { kind: 'add'; org: OrgRef; members: OrgMember[] };

type Found = { term: string; candidates: Candidate[] };

/**
 * Picks people for a new org, or to add to one. Anyone who can't be added stays in the list, greyed out, with the
 * reason why.
 */
export function OrgPeopleDialog({
    mode,
    open,
    onOpenChange,
    onDone,
}: {
    mode: PeopleMode;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDone: (org: OrgRef) => void;
}) {
    const [name, setName] = useState('');
    const [query, setQuery] = useState('');
    const [found, setFound] = useState<Found | null>(null);
    const [picked, setPicked] = useState<User[]>([]);
    const [sharesHistory, setSharesHistory] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const isCreated = useRef(false);
    const term = query.trim().replace(/^@/u, '');
    const orgId = mode.kind === 'add' ? mode.org.id : null;
    const current = mode.kind === 'add' ? mode.members.length : 1;
    const total = current + picked.length;
    const isFull = total >= maxMembers;

    useEffect(() => {
        if (term === '') {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            void searchCandidates(
                term,
                orgId === null ? { for: 'org' } : { org: orgId },
                controller.signal,
            )
                .then((candidates) => setFound({ term, candidates }))
                .catch(() => undefined);
        }, searchDebounce);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [term, orgId]);

    const reset = () => {
        setName('');
        setQuery('');
        setFound(null);
        setPicked([]);
        setSharesHistory(false);
        setError(null);
    };

    const toggle = (person: User) => {
        setError(null);
        setPicked((people) =>
            people.some((item) => item.id === person.id)
                ? people.filter((item) => item.id !== person.id)
                : [...people, person],
        );
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (picked.length === 0) {
            return;
        }

        setIsSaving(true);
        const usernames = picked.map((person) => person.username);

        try {
            if (mode.kind === 'create') {
                const org = await createOrg(name, usernames);

                isCreated.current = true;
                onDone(org);
            } else {
                await addOrgMembers(mode.org, usernames, sharesHistory);
                onDone(mode.org);
            }

            reset();
            onOpenChange(false);
        } catch (failure) {
            setError(errorOf(failure));
        } finally {
            setIsSaving(false);
        }
    };

    const results =
        term !== '' && found?.term === term ? found.candidates : null;

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
                    // The new org's composer takes focus, so it isn't handed back to the button that opened this.
                    if (isCreated.current) {
                        event.preventDefault();
                        isCreated.current = false;
                    }
                }}
                data-test="org-people"
            >
                <form
                    onSubmit={(event) => void submit(event)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="space-y-3 border-b px-5 pt-5 pb-4">
                        <div className="space-y-1">
                            <DialogTitle>
                                {mode.kind === 'create'
                                    ? 'New org'
                                    : 'Add people'}
                            </DialogTitle>
                            <DialogDescription className="flex justify-between gap-3">
                                <span>
                                    {mode.kind === 'create'
                                        ? 'You maintain it. Everyone you add contributes.'
                                        : 'They join as contributors.'}
                                </span>
                                <span
                                    className={cn(
                                        'min-w-[7ch] shrink-0 text-right font-mono text-xs tabular-nums',
                                        { 'text-foreground': isFull },
                                    )}
                                    data-test="org-people-count"
                                >
                                    {total}/{maxMembers}
                                </span>
                            </DialogDescription>
                        </div>
                        {mode.kind === 'create' && (
                            <input
                                value={name}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                maxLength={60}
                                placeholder="Name it (optional)"
                                aria-label="Org name"
                                className="h-9 w-full rounded-md border bg-card px-3 text-base outline-none focus-visible:border-primary md:text-sm"
                                data-test="new-org-name"
                            />
                        )}
                        <label className="relative flex items-center">
                            <span className="sr-only">Find people</span>
                            <Search
                                aria-hidden
                                className="pointer-events-none absolute left-3 size-4 text-muted-foreground"
                            />
                            <input
                                autoFocus
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                maxLength={100}
                                placeholder="Find people by name or @handle"
                                autoComplete="off"
                                spellCheck={false}
                                className="h-9 w-full rounded-md border bg-card pr-3 pl-9 text-base outline-none focus-visible:border-primary md:text-sm"
                                data-test="org-people-search"
                            />
                        </label>
                        <div className="flex max-h-15.5 min-h-7 flex-wrap gap-1.5 overflow-y-auto overscroll-contain">
                            {picked.length === 0 ? (
                                <span className="font-mono text-xs leading-7 text-muted-foreground">
                                    nobody picked yet
                                </span>
                            ) : (
                                picked.map((person) => (
                                    <button
                                        key={person.id}
                                        type="button"
                                        onClick={() => toggle(person)}
                                        aria-label={`Remove @${person.username}`}
                                        className="flex h-7 max-w-40 items-center gap-1.5 rounded-md bg-muted pr-2 pl-1 text-xs hover:bg-accent"
                                    >
                                        <UserAvatar
                                            user={person}
                                            className="size-5"
                                        />
                                        <span className="truncate font-mono">
                                            @{person.username}
                                        </span>
                                        <X className="size-3 shrink-0" />
                                    </button>
                                ))
                            )}
                        </div>
                    </div>

                    <ul
                        aria-label="People"
                        className="min-h-40 flex-1 overflow-y-auto overscroll-contain py-2"
                    >
                        {results === null ? (
                            <li className="px-5 py-8 text-center text-sm text-muted-foreground">
                                {term === ''
                                    ? 'Type a name or handle'
                                    : 'Searching…'}
                            </li>
                        ) : results.length === 0 ? (
                            <li
                                className="truncate px-5 py-8 text-center font-mono text-[13px] text-muted-foreground"
                                title={`fatal: nothing matches '${term}'`}
                            >
                                fatal: nothing matches '{term}'
                            </li>
                        ) : (
                            results.map(({ user: person, reason }) => {
                                const isPicked = picked.some(
                                    (item) => item.id === person.id,
                                );
                                const why =
                                    reason ??
                                    (isFull && !isPicked
                                        ? `the org is full (${maxMembers})`
                                        : null);

                                return (
                                    <li key={person.id}>
                                        <CandidateRow
                                            user={person}
                                            reason={why}
                                            isPicked={isPicked}
                                            onToggle={() => toggle(person)}
                                        />
                                    </li>
                                );
                            })
                        )}
                    </ul>

                    <div className="space-y-3 border-t px-5 py-3">
                        {mode.kind === 'add' && (
                            <label className="flex items-start gap-2.5 text-sm">
                                <Checkbox
                                    checked={sharesHistory}
                                    onCheckedChange={(checked) =>
                                        setSharesHistory(checked === true)
                                    }
                                    className="mt-0.5"
                                    data-test="org-share-history"
                                />
                                <span>
                                    Share history
                                    <span className="block text-xs text-muted-foreground">
                                        They can read everything pushed before
                                        they joined.
                                    </span>
                                </span>
                            </label>
                        )}
                        <div className="flex items-center gap-3">
                            <p
                                role="alert"
                                className="min-w-0 flex-1 truncate font-mono text-xs text-destructive"
                                title={error ?? undefined}
                            >
                                {error}
                            </p>
                            <Button
                                type="submit"
                                className="min-w-28"
                                disabled={isSaving || picked.length === 0}
                                data-test="org-people-submit"
                            >
                                {mode.kind === 'create'
                                    ? 'Create org'
                                    : `Add ${picked.length || ''}`.trim()}
                            </Button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function CandidateRow({
    user,
    reason,
    isPicked,
    onToggle,
    trailing,
}: {
    user: User;
    reason: string | null;
    isPicked?: boolean;
    onToggle: () => void;
    trailing?: ReactNode;
}) {
    const isDisabled = reason !== null && !isPicked;

    return (
        <button
            type="button"
            onClick={onToggle}
            disabled={isDisabled}
            aria-pressed={isPicked}
            className="flex w-full items-center gap-3 px-5 py-2 text-left transition-colors not-disabled:hover:bg-accent disabled:cursor-not-allowed"
            data-test="candidate"
        >
            <UserAvatar
                user={user}
                className={cn('size-9 shrink-0', { 'opacity-50': isDisabled })}
            />
            <span
                className={cn('min-w-0 flex-1', { 'opacity-60': isDisabled })}
            >
                <span className="block truncate text-sm font-semibold">
                    {user.name}
                </span>
                <span className="flex min-w-0 items-center gap-1 font-mono text-xs text-muted-foreground">
                    <span className="truncate">@{user.username}</span>
                    {isDisabled && (
                        <span
                            className="truncate"
                            title={reason}
                            data-test="candidate-reason"
                        >
                            · {reason}
                        </span>
                    )}
                </span>
            </span>
            {trailing ??
                (isPicked !== undefined && (
                    <span
                        aria-hidden
                        className={cn(
                            'grid size-5 shrink-0 place-items-center rounded-sm border',
                            {
                                'border-primary bg-primary text-primary-foreground':
                                    isPicked,
                                invisible: isDisabled,
                            },
                        )}
                    >
                        {isPicked && <Check className="size-3.5" />}
                    </span>
                ))}
        </button>
    );
}
