import { Link, router } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Search } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useState } from 'react';
import UserSearchController from '@/actions/App/Http/Controllers/UserSearchController';
import { DialogOverlay, DialogPortal } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { cn, laneColor } from '@/lib/utils';
import { show } from '@/routes/user';
import type { User } from '@/types';

const debounce = 120;

export function UserSearchDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<User[]>([]);
    const [searched, setSearched] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);

    const term = query.trim().replace(/^@/u, '');

    useEffect(() => {
        if (term === '') {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => {
            setIsLoading(true);

            fetch(UserSearchController.index({ query: { q: term } }).url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            })
                .then((response) => response.json())
                .then((data: { users: User[] }) => {
                    setResults(data.users);
                    setSearched(term);
                    setActiveIndex(0);
                })
                .catch(() => undefined)
                .finally(() => setIsLoading(false));
        }, debounce);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [term]);

    const visibleResults = term !== '' && term === searched ? results : [];

    const close = () => {
        onOpenChange(false);
        setQuery('');
        setResults([]);
        setSearched('');
    };

    const openProfile = (user: User) => {
        close();
        router.visit(show(user));
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (visibleResults.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex(
                (index) =>
                    (index + step + visibleResults.length) %
                    visibleResults.length,
            );
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            openProfile(visibleResults[activeIndex]);
        }
    };

    return (
        <DialogPrimitive.Root
            open={open}
            onOpenChange={(isOpen) => (isOpen ? onOpenChange(true) : close())}
        >
            <DialogPortal>
                <DialogOverlay />
                <DialogPrimitive.Content
                    className="fixed top-[12vh] left-1/2 z-50 w-full max-w-[calc(100%-2rem)] -translate-x-1/2 overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-[0_24px_48px_-16px_rgb(0_0_0/0.4)] duration-200 motion-safe:data-[state=closed]:animate-out motion-safe:data-[state=closed]:fade-out-0 motion-safe:data-[state=closed]:zoom-out-95 motion-safe:data-[state=open]:animate-in motion-safe:data-[state=open]:fade-in-0 motion-safe:data-[state=open]:zoom-in-95 sm:max-w-xl"
                    data-test="user-search"
                >
                    <DialogPrimitive.Title className="sr-only">
                        Search developers
                    </DialogPrimitive.Title>
                    <DialogPrimitive.Description className="sr-only">
                        Find people by name or GitHub username.
                    </DialogPrimitive.Description>

                    <div className="flex items-center gap-3 border-b px-4">
                        <Search className="size-5 shrink-0 text-muted-foreground" />
                        <input
                            autoFocus
                            maxLength={100}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder="Search by name or @handle"
                            role="combobox"
                            aria-expanded={visibleResults.length > 0}
                            aria-controls="user-search-results"
                            aria-activedescendant={
                                visibleResults[activeIndex]
                                    ? `user-search-${visibleResults[activeIndex].id}`
                                    : undefined
                            }
                            aria-autocomplete="list"
                            autoComplete="off"
                            spellCheck={false}
                            className="h-14 min-w-0 flex-1 bg-transparent text-[17px] outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                            data-test="user-search-input"
                        />
                        {isLoading && (
                            <Spinner className="size-4 text-muted-foreground" />
                        )}
                        <kbd className="hidden rounded border border-b-2 bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground sm:block">
                            esc
                        </kbd>
                    </div>

                    {visibleResults.length > 0 ? (
                        <ul
                            id="user-search-results"
                            role="listbox"
                            aria-label="Developers"
                            className="max-h-[min(26rem,60vh)] overflow-y-auto p-2"
                        >
                            {visibleResults.map((user, index) => (
                                <li
                                    key={user.id}
                                    id={`user-search-${user.id}`}
                                    role="option"
                                    aria-selected={index === activeIndex}
                                >
                                    <Link
                                        href={show(user)}
                                        tabIndex={-1}
                                        onMouseMove={() =>
                                            setActiveIndex(index)
                                        }
                                        onClick={close}
                                        className={cn(
                                            'flex items-center gap-3 rounded-lg px-3 py-2.5',
                                            {
                                                'bg-accent':
                                                    index === activeIndex,
                                            },
                                        )}
                                    >
                                        <UserAvatar
                                            user={user}
                                            className="size-9 shrink-0"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-baseline gap-1.5">
                                                <span className="truncate font-semibold">
                                                    {user.name}
                                                </span>
                                                <span className="flex shrink-0 items-center gap-1 font-mono text-[13px] text-muted-foreground">
                                                    <span
                                                        aria-hidden
                                                        className="size-1.5 rounded-full"
                                                        style={{
                                                            background:
                                                                laneColor(
                                                                    user.lane,
                                                                ),
                                                        }}
                                                    />
                                                    @{user.username}
                                                </span>
                                            </span>
                                            {user.bio && (
                                                <span className="block truncate text-sm text-muted-foreground">
                                                    {user.bio}
                                                </span>
                                            )}
                                        </span>
                                        <kbd
                                            aria-hidden
                                            className={cn(
                                                'shrink-0 font-mono text-xs text-muted-foreground opacity-0',
                                                {
                                                    'opacity-100':
                                                        index === activeIndex,
                                                },
                                            )}
                                        >
                                            ↵
                                        </kbd>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div
                            role="status"
                            className="px-5 py-8 text-center text-sm text-muted-foreground"
                        >
                            {term === '' ? (
                                'Type a name or @handle.'
                            ) : searched === term && !isLoading ? (
                                <span className="font-mono text-[13px]">
                                    fatal: no developers match '{term}'
                                </span>
                            ) : (
                                'Searching…'
                            )}
                        </div>
                    )}

                    <div className="hidden items-center gap-4 border-t bg-muted/40 px-4 py-2 text-xs text-muted-foreground sm:flex">
                        <span className="flex items-center gap-1.5">
                            <kbd className="font-mono">↑↓</kbd> to move
                        </span>
                        <span className="flex items-center gap-1.5">
                            <kbd className="font-mono">↵</kbd> to open
                        </span>
                        <span className="ml-auto flex items-center gap-1.5">
                            <kbd className="font-mono">⌘K</kbd> anywhere
                        </span>
                    </div>
                </DialogPrimitive.Content>
            </DialogPortal>
        </DialogPrimitive.Root>
    );
}
