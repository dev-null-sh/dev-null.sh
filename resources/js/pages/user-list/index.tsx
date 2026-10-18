import { Form, Head, Link } from '@inertiajs/react';
import { LockKeyhole, Pin, PinOff } from 'lucide-react';
import UserListController from '@/actions/App/Http/Controllers/UserListController';
import UserListPinController from '@/actions/App/Http/Controllers/UserListPinController';
import { EmptyFeed } from '@/components/commit-feed';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { visitWithErrorToast } from '@/lib/user-lists';
import type { UserList } from '@/types';

export default function Index({
    lists,
    maxLists,
    maxPinned,
}: {
    lists: (UserList & { members_count: number })[];
    maxLists: number;
    maxPinned: number;
}) {
    return (
        <>
            <Head title="Lists" />

            <header className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Lists</h1>
                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <LockKeyhole aria-hidden className="size-3.5" />
                    Only you
                </span>
            </header>

            <p className="border-b px-4 py-3 text-sm text-muted-foreground sm:px-6">
                Group developers to read only their commits. Pin up to{' '}
                {maxPinned} lists to keep them next to your logs. Nobody is told
                when you add them.
            </p>

            {lists.length < maxLists && (
                <Form
                    action={UserListController.store()}
                    resetOnSuccess
                    className="border-b px-4 py-3 sm:px-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex gap-2">
                                <label htmlFor="list-name" className="sr-only">
                                    List name
                                </label>
                                <input
                                    id="list-name"
                                    name="name"
                                    type="text"
                                    required
                                    maxLength={40}
                                    placeholder="Name a new list"
                                    aria-invalid={!!errors.name}
                                    className="h-9 min-w-0 flex-1 rounded-md border bg-background px-3 text-base outline-none placeholder:text-muted-foreground focus:border-ring md:text-sm"
                                    data-test="list-name"
                                />
                                <Button type="submit" disabled={processing}>
                                    Create
                                </Button>
                            </div>
                            <InputError
                                message={errors.name}
                                className="mt-2"
                            />
                        </>
                    )}
                </Form>
            )}

            {lists.length === 0 ? (
                <EmptyFeed command="fatal: no lists yet">
                    Name a list above, then add developers to it from the menu
                    on their profiles.
                </EmptyFeed>
            ) : (
                <ul className="divide-y border-b">
                    {lists.map((list) => {
                        const isPinned = list.pinned_at !== null;

                        return (
                            <li
                                key={list.id}
                                className="flex items-center gap-3 px-4 py-3 sm:px-6"
                            >
                                <Link
                                    href={UserListController.show(list.id)}
                                    className="min-w-0 flex-1"
                                >
                                    <p className="truncate font-medium hover:underline">
                                        {list.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {list.members_count}{' '}
                                        {list.members_count === 1
                                            ? 'developer'
                                            : 'developers'}
                                    </p>
                                </Link>
                                <Button
                                    variant={isPinned ? 'secondary' : 'ghost'}
                                    size="icon"
                                    aria-label={
                                        isPinned
                                            ? `Unpin ${list.name}`
                                            : `Pin ${list.name}`
                                    }
                                    aria-pressed={isPinned}
                                    onClick={() =>
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
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </>
    );
}

Index.layout = AppLayout;
