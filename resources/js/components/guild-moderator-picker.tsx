import { usePage } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { index as memberSuggestions } from '@/actions/App/Http/Controllers/GuildMemberSuggestionController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { toastWhenThrottled } from '@/lib/throttle';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

type Member = Pick<User, 'id' | 'handle' | 'name' | 'avatar_url'>;

/**
 * Hands moderation to another member. Only people who already joined the guild show up, and the server checks it again on save.
 */
export function GuildModeratorPicker({
    handle,
    fieldClass,
    error,
    onChange,
}: {
    handle: string;
    fieldClass: string;
    error?: string;
    onChange?: () => void;
}) {
    const me = usePage().props.auth.user;
    const id = useId();
    const [moderator, setModerator] = useState<Member | null>(me);
    const [searching, setSearching] = useState(false);
    const [query, setQuery] = useState('');
    const [members, setMembers] = useState<Member[] | null>(null);

    useEffect(() => {
        if (!searching) return;

        const controller = new AbortController();
        const timeout = setTimeout(
            () => {
                fetch(
                    memberSuggestions.url(handle, {
                        query: { q: query.trim() || undefined },
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                )
                    .then(async (response) => {
                        if (!response.ok) {
                            toastWhenThrottled(response);
                            throw new Error('Unable to load members');
                        }

                        return (await response.json()) as {
                            members: Member[];
                        };
                    })
                    .then((data) => setMembers(data.members))
                    .catch(() => {
                        if (!controller.signal.aborted) setMembers([]);
                    });
            },
            query ? 200 : 0,
        );

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [handle, query, searching]);

    if (!me) return null;

    const handover = moderator !== null && moderator.id !== me.id;

    const pick = (member: Member) => {
        setModerator(member);
        setSearching(false);
        setQuery('');
        setMembers(null);
        onChange?.();
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={searching ? `${id}-search` : undefined}>
                Moderator
            </Label>
            <input
                type="hidden"
                name="moderator_id"
                value={moderator?.id ?? me.id}
            />
            {searching ? (
                <div className="grid gap-2">
                    <input
                        id={`${id}-search`}
                        type="search"
                        autoFocus
                        autoComplete="off"
                        maxLength={60}
                        placeholder="Search members"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                if (members?.[0]) pick(members[0]);
                            }
                        }}
                        className={fieldClass}
                        aria-controls={`${id}-members`}
                        aria-describedby={`${id}-error`}
                    />
                    <ul
                        id={`${id}-members`}
                        className="grid max-h-56 gap-1 overflow-y-auto"
                        aria-busy={members === null}
                    >
                        {members === null ? (
                            <li className="flex justify-center py-3">
                                <Spinner />
                            </li>
                        ) : members.length === 0 ? (
                            <li className="px-1 py-2 text-sm text-muted-foreground">
                                {query
                                    ? 'No members match.'
                                    : 'Nobody else has joined yet.'}
                            </li>
                        ) : (
                            members.map((member) => (
                                <li key={member.id}>
                                    <button
                                        type="button"
                                        onClick={() => pick(member)}
                                        className="flex w-full min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                                    >
                                        <MemberRow member={member} />
                                    </button>
                                </li>
                            ))
                        )}
                    </ul>
                    <div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearching(false);
                                setQuery('');
                                setMembers(null);
                            }}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            ) : (
                <div
                    className={cn(
                        'flex min-w-0 items-center gap-2 rounded-md border px-3 py-2',
                        handover && 'border-amber-500/60',
                    )}
                >
                    <MemberRow member={moderator ?? me} />
                    {!handover && (
                        <span className="shrink-0 text-xs text-muted-foreground">
                            You
                        </span>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="ml-auto shrink-0"
                        onClick={() => setSearching(true)}
                    >
                        Change
                    </Button>
                </div>
            )}
            {handover && !searching && (
                <p className="text-xs text-muted-foreground">
                    Once you save, @{moderator.handle} moderates #{handle} and
                    you can no longer edit it.{' '}
                    <button
                        type="button"
                        className="underline underline-offset-2 hover:text-foreground"
                        onClick={() => pick(me)}
                    >
                        Keep moderating
                    </button>
                </p>
            )}
            <div id={`${id}-error`} className="min-h-5">
                <InputError message={error} />
            </div>
        </div>
    );
}

function MemberRow({ member }: { member: Member }) {
    return (
        <>
            <UserAvatar user={member} className="size-7 shrink-0" />
            <span className="min-w-0 flex-1 truncate text-sm">
                <span className="font-medium">{member.name}</span>{' '}
                <span className="text-muted-foreground">@{member.handle}</span>
            </span>
        </>
    );
}
