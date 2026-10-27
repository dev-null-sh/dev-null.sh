import { Head, Link, router } from '@inertiajs/react';
import { Ellipsis, LockKeyhole, Pin, PinOff, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import UserListController from '@/actions/App/Http/Controllers/UserListController';
import UserListMemberController from '@/actions/App/Http/Controllers/UserListMemberController';
import UserListPinController from '@/actions/App/Http/Controllers/UserListPinController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { LogTabs } from '@/components/log-tabs';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import AppLayout from '@/layouts/app-layout';
import { visitWithErrorToast } from '@/lib/user-lists';
import type {
    Commit,
    CursorPaginated,
    PinnedList,
    User,
    UserList,
} from '@/types';

export default function Show({
    list,
    members,
    pinnedLists,
    commits,
}: {
    list: UserList & { members_count: number };
    members: User[];
    pinnedLists: PinnedList[];
    commits: CursorPaginated<Commit>;
}) {
    const isPinned = list.pinned_at !== null;
    // Deleting a list can't be undone, so it asks first, like deleting a commit.
    const [isConfirmingDelete, setIsConfirmingDelete] = useState(false);

    return (
        <>
            <Head title={list.name} />

            <div className="sticky top-0 z-10 bg-card">
                {isPinned ? (
                    <LogTabs active={list.id} pinnedLists={pinnedLists} />
                ) : (
                    <div className="flex h-14 items-center border-b px-4 sm:px-6">
                        <Link
                            href={UserListController.index()}
                            className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                        >
                            Lists
                        </Link>
                    </div>
                )}
            </div>

            <header className="border-b px-4 py-4 sm:px-6">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-semibold tracking-tight">
                            {list.name}
                        </h1>
                        <p className="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <LockKeyhole aria-hidden className="size-3.5" />
                            Only you · {list.members_count}{' '}
                            {list.members_count === 1
                                ? 'developer'
                                : 'developers'}
                        </p>
                    </div>

                    <DropdownMenu modal={false}>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label={`Options for ${list.name}`}
                            >
                                <Ellipsis />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                            <DropdownMenuItem
                                onSelect={() =>
                                    visitWithErrorToast(
                                        isPinned
                                            ? UserListPinController.destroy(
                                                  list.id,
                                              )
                                            : UserListPinController.update(
                                                  list.id,
                                              ),
                                    )
                                }
                            >
                                {isPinned ? <PinOff /> : <Pin />}
                                {isPinned
                                    ? 'Unpin from the logs'
                                    : 'Pin next to the logs'}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setIsConfirmingDelete(true)}
                            >
                                <Trash2 />
                                Delete list
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {isConfirmingDelete && (
                    <div
                        role="group"
                        aria-label="Confirm delete"
                        onKeyDown={(event) => {
                            if (event.key === 'Escape') {
                                setIsConfirmingDelete(false);
                            }
                        }}
                        className="mt-3 flex min-h-12 flex-wrap items-center gap-x-3 gap-y-2 rounded-md bg-destructive/8 px-3 py-2 text-sm"
                    >
                        <span className="mr-auto">
                            Delete this list? This can't be undone.
                        </span>
                        <span className="flex shrink-0 gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                autoFocus
                                onClick={() => setIsConfirmingDelete(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() =>
                                    router.visit(
                                        UserListController.destroy(list.id),
                                    )
                                }
                                data-test="confirm-delete-list-button"
                            >
                                Delete
                            </Button>
                        </span>
                    </div>
                )}

                {members.length > 0 && (
                    <ul
                        aria-label="Developers on this list"
                        className="mt-3 flex flex-wrap gap-1.5"
                    >
                        {members.map((member) => (
                            <li
                                key={member.id}
                                className="flex items-center gap-1.5 rounded-full border py-0.5 pr-1 pl-0.5 text-sm"
                            >
                                <UserPreview user={member}>
                                    <span className="flex items-center gap-1.5">
                                        <UserAvatar
                                            user={member}
                                            className="size-5"
                                        />
                                        <span className="font-mono text-[13px]">
                                            @{member.handle}
                                        </span>
                                    </span>
                                </UserPreview>
                                <Link
                                    href={UserListMemberController.destroy([
                                        list.id,
                                        member.handle,
                                    ])}
                                    as="button"
                                    preserveScroll
                                    className="rounded-full p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                                    aria-label={`Remove @${member.handle} from ${list.name}`}
                                >
                                    <X className="size-3.5" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </header>

            <CommitFeed
                commits={commits}
                end="That's the root commit. There's nothing before it."
                empty={
                    <EmptyFeed command="fatal: this list does not have any commits yet">
                        {members.length === 0
                            ? 'Add developers to this list from the menu on their profiles, and their commits land here.'
                            : 'Nobody on this list has committed anything yet.'}
                    </EmptyFeed>
                }
            />
        </>
    );
}

Show.layout = AppLayout;
