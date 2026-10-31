import { Form, Head, Link, router } from '@inertiajs/react';
import type { KeyboardEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import MentionSuggestionController from '@/actions/App/Http/Controllers/MentionSuggestionController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { UserAvatar } from '@/components/user-avatar';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import OrganizationSettingsLayout from '@/layouts/organization-settings/layout';
import { toastWhenThrottled } from '@/lib/throttle';
import { cn } from '@/lib/utils';
import type { Organization, User } from '@/types';

type Role = 'admin' | 'member';

type Member = User & { role: Role; joined_at: string };

type OrganizationWithId = Organization & { id: string };

export default function Index({
    organization,
    role,
    members,
}: {
    organization: OrganizationWithId;
    role: Role;
    members: Member[];
}) {
    const headingRef = useRef<HTMLHeadingElement>(null);
    const isAdmin = role === 'admin';
    const adminCount = members.filter(
        (member) => member.role === 'admin',
    ).length;
    const isOnlyAdmin = isAdmin && adminCount === 1;

    // A removed row unmounts with the focus inside it, so it moves to the next row, or the heading...
    const focusAfter = (handle: string) => {
        const index = members.findIndex((member) => member.handle === handle);
        const next = members[index + 1] ?? members[index - 1];

        const target = next
            ? document.querySelector<HTMLElement>(
                  `[data-member="${next.handle}"] [data-remove]`,
              )
            : null;

        (target ?? headingRef.current)?.focus({ preventScroll: true });
    };

    return (
        <>
            <Head title={`Members of @${organization.handle}`} />

            <section className="px-4 py-6 sm:px-6">
                <header className="mb-5">
                    <h2
                        ref={headingRef}
                        tabIndex={-1}
                        className="text-lg font-semibold tracking-tight outline-none"
                    >
                        Members
                    </h2>
                    <p className="mt-1 max-w-prose text-sm text-pretty text-muted-foreground">
                        {isAdmin
                            ? 'Admins add and remove members, and choose who else is an admin. Only members see this list.'
                            : 'Only members see this list. Admins add and remove members.'}
                    </p>
                </header>

                {isAdmin && (
                    <AddMemberForm
                        organization={organization}
                        members={members}
                    />
                )}

                <ul className="overflow-hidden rounded-lg border">
                    {members.map((member) => (
                        <MemberRow
                            key={member.id}
                            organization={organization}
                            member={member}
                            isAdmin={isAdmin}
                            isLastAdmin={
                                member.role === 'admin' && adminCount === 1
                            }
                            onRemoved={() => focusAfter(member.handle)}
                        />
                    ))}
                </ul>

                {isOnlyAdmin && (
                    <p className="mt-3 max-w-prose text-sm text-pretty text-muted-foreground">
                        You're the only admin of @{organization.handle}, so you
                        can't leave or become a member. Make someone else an
                        admin first.
                    </p>
                )}
            </section>
        </>
    );
}

/**
 * Admins add people by their handle, searching people by handle or name as they type. They join as members, and
 * can then be made admins.
 */
function AddMemberForm({
    organization,
    members,
}: {
    organization: OrganizationWithId;
    members: Member[];
}) {
    const id = useId();
    const [handle, setHandle] = useState('');
    const [results, setResults] = useState<User[]>([]);
    const [isOpen, setIsOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const query = handle.trim().replace(/^@/, '');

    useEffect(() => {
        if (query === '') {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            const response = await fetch(
                MentionSuggestionController.index({ query: { q: query } }).url,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                },
            ).catch(() => null);

            if (!response?.ok) {
                if (response) {
                    toastWhenThrottled(response);
                }

                return;
            }

            const { users } = (await response.json()) as { users: User[] };

            setResults(users);
            setActiveIndex(0);
        }, 120);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [query]);

    // The last results stay up while the next ones load, so the list doesn't flash empty between keystrokes.
    const suggestions =
        query === ''
            ? []
            : results.filter(
                  (user) => !members.some((member) => member.id === user.id),
              );
    const isListed = isOpen && suggestions.length > 0;
    const selectedIndex = Math.min(activeIndex, suggestions.length - 1);

    const select = (user: User) => {
        setHandle(user.handle);
        setIsOpen(false);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (!isListed) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex(
                (selectedIndex + step + suggestions.length) %
                    suggestions.length,
            );
        } else if (event.key === 'Enter' || event.key === 'Tab') {
            // A handle already typed in full is added on Enter, instead of picked again...
            if (
                event.key === 'Enter' &&
                suggestions[selectedIndex].handle.toLowerCase() ===
                    query.toLowerCase()
            ) {
                setIsOpen(false);

                return;
            }

            event.preventDefault();
            select(suggestions[selectedIndex]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setIsOpen(false);
        }
    };

    return (
        <Form
            {...OrganizationMemberController.store.form(organization.id)}
            options={{ preserveScroll: true }}
            onSuccess={() => setHandle('')}
            className="mb-5"
        >
            {({ errors, processing }) => (
                <>
                    <label htmlFor="member-handle" className="sr-only">
                        Handle or name of the person to add
                    </label>
                    <div className="flex max-w-md gap-2">
                        {/* The list overlays the members below, so opening it moves nothing. */}
                        <div className="relative min-w-0 flex-1">
                            <input
                                id="member-handle"
                                name="handle"
                                value={handle}
                                onChange={(event) => {
                                    setHandle(event.target.value);
                                    setIsOpen(true);
                                }}
                                onFocus={() => setIsOpen(true)}
                                onBlur={() => setIsOpen(false)}
                                onKeyDown={handleKeyDown}
                                required
                                maxLength={40}
                                autoComplete="off"
                                autoCapitalize="none"
                                spellCheck={false}
                                placeholder="Search by @handle or name"
                                role="combobox"
                                aria-expanded={isListed}
                                aria-autocomplete="list"
                                aria-controls={
                                    isListed ? `${id}-suggestions` : undefined
                                }
                                aria-activedescendant={
                                    isListed
                                        ? `${id}-${suggestions[selectedIndex].id}`
                                        : undefined
                                }
                                aria-invalid={errors.handle ? true : undefined}
                                aria-describedby="member-handle-error"
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm dark:bg-input/30"
                            />

                            {isListed && (
                                <ul
                                    id={`${id}-suggestions`}
                                    role="listbox"
                                    aria-label="People to add"
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    className="absolute inset-x-0 top-full z-50 mt-1 max-h-72 overflow-y-auto overscroll-contain rounded-lg border bg-popover p-1.5 text-popover-foreground shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)]"
                                >
                                    {suggestions.map((user, index) => (
                                        <li
                                            key={user.id}
                                            id={`${id}-${user.id}`}
                                            role="option"
                                            aria-selected={
                                                index === selectedIndex
                                            }
                                            onMouseMove={() =>
                                                setActiveIndex(index)
                                            }
                                            onClick={() => select(user)}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-2.5 rounded-sm px-2.5 py-2',
                                                {
                                                    'bg-accent':
                                                        index === selectedIndex,
                                                },
                                            )}
                                        >
                                            <UserAvatar
                                                user={user}
                                                className="size-8 shrink-0"
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span
                                                    className="block truncate text-sm font-semibold"
                                                    title={user.name}
                                                >
                                                    {user.name}
                                                </span>
                                                <span className="block truncate font-mono text-xs text-muted-foreground">
                                                    @{user.handle}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <Button type="submit" disabled={processing}>
                            Add
                        </Button>
                    </div>
                    {/* The line is kept, so an error doesn't push the list down. */}
                    <p
                        id="member-handle-error"
                        className="mt-1 h-5 truncate text-sm text-destructive"
                        title={errors.handle}
                    >
                        {errors.handle}
                    </p>
                </>
            )}
        </Form>
    );
}

function MemberRow({
    organization,
    member,
    isAdmin,
    isLastAdmin,
    onRemoved,
}: {
    organization: OrganizationWithId;
    member: Member;
    isAdmin: boolean;
    isLastAdmin: boolean;
    onRemoved: () => void;
}) {
    const { user } = useUser();
    const { formatFullDateTime, formatRelativeTime } = useDateTime();
    const now = useNow();
    const isSelf = member.id === user.id;
    const [removal, setRemoval] = useState<'idle' | 'confirming' | 'removing'>(
        'idle',
    );
    const [hasCancelled, setHasCancelled] = useState(false);
    // The role the select shows until the change is saved, so it doesn't flip back meanwhile.
    const [pendingRole, setPendingRole] = useState<Role | null>(null);
    const route = { organization: organization.id, member: member.handle };
    const lastAdminReason = `@${organization.handle} needs at least one admin.`;
    const joined = formatRelativeTime(member.joined_at, now);

    const changeRole = (role: Role) => {
        setPendingRole(role);

        router.visit(OrganizationMemberController.update(route), {
            data: { role },
            preserveScroll: true,
            // Another admin may have changed the members since the page loaded...
            onError: (errors) => toast.error(Object.values(errors)[0]),
            onFinish: () => setPendingRole(null),
        });
    };

    const cancel = () => {
        setRemoval('idle');
        setHasCancelled(true);
    };

    const remove = () => {
        setRemoval('removing');

        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        window.setTimeout(
            () => {
                let isRemoved = false;

                router.visit(OrganizationMemberController.destroy(route), {
                    preserveScroll: true,
                    // A removal the server refused, as another admin left meanwhile, keeps the member listed...
                    onSuccess: (page) => {
                        isRemoved = !(
                            page.props.members as Member[] | undefined
                        )?.some(({ id }) => id === member.id);

                        if (isRemoved && !isSelf) {
                            onRemoved();
                        }
                    },
                    onError: (errors) => toast.error(Object.values(errors)[0]),
                    // A visit that failed or was cancelled brings the row back, so it can be removed again...
                    onFinish: () => {
                        if (!isRemoved) {
                            setRemoval('idle');
                        }
                    },
                });
            },
            prefersReducedMotion ? 0 : 700,
        );
    };

    const removeLabel = isSelf ? 'Leave' : 'Remove';
    const confirmation = isSelf
        ? `Leave @${organization.handle}? An admin would have to add you back.`
        : `Remove @${member.handle} from @${organization.handle}?`;

    return (
        <li
            data-member={member.handle}
            className={cn(
                'grid border-b transition-[grid-template-rows,opacity,background-color] duration-300 ease-out last:border-b-0',
                removal === 'removing'
                    ? 'grid-rows-[0fr] opacity-0 delay-400'
                    : 'grid-rows-[1fr]',
                { 'bg-destructive/8': removal !== 'idle' },
            )}
        >
            <div className="min-h-0 overflow-hidden">
                {/* On phones the controls get their own line under the name, which stays readable. */}
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
                    <Link href={show(member.handle)} className="shrink-0">
                        <UserAvatar
                            user={member}
                            className="size-9"
                            pixels={72}
                        />
                    </Link>

                    <div className="min-w-0 flex-1">
                        <p className="truncate font-medium" title={member.name}>
                            <Link
                                href={show(member.handle)}
                                className="hover:underline"
                            >
                                {member.name}
                            </Link>
                            {isSelf && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    (you)
                                </span>
                            )}
                        </p>

                        {removal === 'idle' && (
                            <p className="h-5 truncate text-sm leading-5 text-muted-foreground">
                                <span className="font-mono text-[13px]">
                                    @{member.handle}
                                </span>
                                <span aria-hidden> · </span>
                                joined{' '}
                                <time
                                    dateTime={member.joined_at}
                                    title={formatFullDateTime(member.joined_at)}
                                    className="font-mono text-[13px] tabular-nums"
                                >
                                    {joined === 'now'
                                        ? 'just now'
                                        : /^\d+[mhd]$/.test(joined)
                                          ? `${joined} ago`
                                          : joined}
                                </time>
                            </p>
                        )}

                        {removal === 'confirming' && (
                            <p
                                className="h-5 truncate text-sm leading-5"
                                title={confirmation}
                            >
                                {confirmation}
                            </p>
                        )}

                        {removal === 'removing' && (
                            <p
                                className="h-5 truncate font-mono text-[13px] leading-5 text-muted-foreground"
                                role="status"
                            >
                                rm @{member.handle} → /dev/null
                            </p>
                        )}
                    </div>

                    {removal === 'idle' && (
                        <div className="flex w-full shrink-0 items-center gap-2 pl-12 sm:w-auto sm:pl-0">
                            {isAdmin ? (
                                <Select
                                    value={pendingRole ?? member.role}
                                    onValueChange={(role) =>
                                        changeRole(role as Role)
                                    }
                                    disabled={isLastAdmin}
                                >
                                    <SelectTrigger
                                        size="sm"
                                        aria-label={`Role of @${member.handle}`}
                                        title={
                                            isLastAdmin
                                                ? lastAdminReason
                                                : undefined
                                        }
                                        className="w-[6.5rem]"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">
                                            Admin
                                        </SelectItem>
                                        <SelectItem value="member">
                                            Member
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            ) : (
                                <span className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                    {member.role === 'admin'
                                        ? 'Admin'
                                        : 'Member'}
                                </span>
                            )}

                            {(isAdmin || isSelf) && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setRemoval('confirming')}
                                    disabled={isLastAdmin}
                                    title={
                                        isLastAdmin
                                            ? lastAdminReason
                                            : undefined
                                    }
                                    className="w-[4.75rem] hover:bg-destructive/10 hover:text-destructive"
                                    // Focus comes back here when the confirm is cancelled...
                                    autoFocus={hasCancelled}
                                    data-remove
                                >
                                    {removeLabel}
                                </Button>
                            )}
                        </div>
                    )}

                    {removal === 'confirming' && (
                        <div
                            role="group"
                            aria-label={`Confirm ${removeLabel.toLowerCase()}`}
                            onKeyDown={(event) => {
                                if (event.key === 'Escape') {
                                    cancel();
                                }
                            }}
                            className="flex w-full shrink-0 gap-2 pl-12 sm:w-auto sm:pl-0"
                        >
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={remove}
                            >
                                {removeLabel}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                autoFocus
                                onClick={cancel}
                            >
                                Cancel
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </li>
    );
}

Index.layout = [AppLayout, OrganizationSettingsLayout];
