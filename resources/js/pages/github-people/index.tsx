import {
    Form,
    Head,
    Link,
    router,
    useForm,
    usePage,
    usePoll,
} from '@inertiajs/react';
import { ArrowLeft, RefreshCw, Users } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/GitHubPeopleController';
import { store as followSelected } from '@/actions/App/Http/Controllers/GitHubPeopleFollowController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { FollowButton } from '@/components/follow-button';
import { GitHubIcon } from '@/components/github-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import { show } from '@/routes/user';
import type { User } from '@/types';

type Connection = User & { is_followed: boolean };

type Discovery = {
    status:
        | 'idle'
        | 'loading'
        | 'ready'
        | 'unavailable'
        | 'rate_limited'
        | 'renamed';
    users: Connection[];
    scanned: number;
    fetched_at: string | null;
    retry_at: string | null;
    truncated: boolean;
};

export default function Index({ discovery }: { discovery: Discovery }) {
    const { errors } = usePage().props;
    const now = useNow();
    const isLoading = discovery.status === 'loading';
    const isRateLimited = discovery.status === 'rate_limited';
    const isRenamed = discovery.status === 'renamed';
    const retryAt = discovery.retry_at ? new Date(discovery.retry_at) : null;
    const isWaiting = retryAt !== null && retryAt > now;
    const { start, stop } = usePoll(
        1500,
        { only: ['discovery'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isLoading) {
            start();
        } else {
            stop();
        }
    }, [isLoading, start, stop]);

    return (
        <>
            <Head title="Find your GitHub people" />

            <header className="sticky top-0 z-10 flex min-h-14 items-center gap-3 border-b bg-card px-4 py-3 sm:px-6">
                <Link
                    href={LogController.show('following')}
                    aria-label="Back to your log"
                    className="shrink-0 text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <h1 className="font-semibold">Find your GitHub people</h1>
            </header>

            <p role="status" className="sr-only">
                {isLoading
                    ? 'Looking for your GitHub people.'
                    : discovery.status === 'ready'
                      ? `Found ${discovery.users.length} GitHub connections on devnull.`
                      : ''}
            </p>

            <section className="border-b px-4 py-6 sm:px-6">
                <GitHubIcon className="mb-4 size-8" />
                <h2 className="text-lg font-semibold">
                    A familiar face in your log.
                </h2>
                <p className="mt-2 text-[15px] leading-relaxed text-muted-foreground">
                    Find people you already follow on GitHub who are here on
                    devnull. We check up to 500 people from your public GitHub
                    following list. You choose who to follow here.
                </p>
                <InputError
                    message={
                        discovery.status !== 'ready'
                            ? errors.user_ids
                            : undefined
                    }
                    className="mt-2"
                />

                {!isLoading && discovery.status !== 'ready' && (
                    <Form
                        {...store.form()}
                        options={{ only: ['discovery'], preserveScroll: true }}
                        className="mt-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Button
                                    type="submit"
                                    variant={
                                        discovery.status === 'idle'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    disabled={processing || isWaiting}
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : discovery.status === 'idle' ? (
                                        <Users />
                                    ) : (
                                        <RefreshCw />
                                    )}
                                    {processing
                                        ? 'Looking…'
                                        : discovery.status === 'idle'
                                          ? 'Find my people'
                                          : 'Try again'}
                                </Button>
                                <InputError
                                    message={errors.discovery}
                                    className="mt-2"
                                />
                            </>
                        )}
                    </Form>
                )}
                {discovery.status === 'ready' && (
                    <Button variant="outline" className="mt-4" asChild>
                        <Link href={LogController.show('following')}>
                            Go to your log
                        </Link>
                    </Button>
                )}
            </section>

            {discovery.status === 'ready' && discovery.truncated && (
                <p
                    role="status"
                    className="border-b px-4 py-4 text-sm text-muted-foreground sm:px-6"
                >
                    Checked {discovery.scanned} GitHub follows. Some matches may
                    be missing.
                </p>
            )}

            {isLoading && (
                <section
                    aria-label="Looking for your GitHub people"
                    aria-busy="true"
                >
                    <div className="flex items-center gap-3 border-b px-4 py-4 text-sm sm:px-6">
                        <Spinner aria-hidden />
                        <p>
                            Looking for your people…
                            <span className="mt-1 block text-muted-foreground">
                                {discovery.scanned} GitHub{' '}
                                {discovery.scanned === 1
                                    ? 'connection'
                                    : 'connections'}{' '}
                                checked.
                            </span>
                        </p>
                    </div>
                    <div aria-hidden className="divide-y">
                        {[0, 1, 2].map((row) => (
                            <div
                                key={row}
                                className="flex items-center gap-3 px-4 py-4 sm:px-6"
                            >
                                <Skeleton className="size-10 rounded-full" />
                                <div className="space-y-2">
                                    <Skeleton className="h-3.5 w-32" />
                                    <Skeleton className="h-3 w-24" />
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {(discovery.status === 'unavailable' ||
                isRateLimited ||
                isRenamed) && (
                <div className="px-4 py-6 text-sm sm:px-6" role="status">
                    <h2 className="font-semibold">
                        {isRenamed
                            ? 'Your GitHub username changed during discovery. Please try again.'
                            : isRateLimited
                              ? 'GitHub needs a moment.'
                              : 'Could not check GitHub right now.'}
                    </h2>
                    <p className="mt-2 text-muted-foreground">
                        {retryAt !== null ? (
                            <>
                                Try again after{' '}
                                <time
                                    dateTime={discovery.retry_at ?? undefined}
                                >
                                    {retryAt.toLocaleTimeString([], {
                                        hour: 'numeric',
                                        minute: '2-digit',
                                    })}
                                </time>
                                .
                            </>
                        ) : (
                            'Please try again in a moment.'
                        )}
                    </p>
                </div>
            )}

            {discovery.status === 'ready' &&
                (discovery.users.length > 0 ? (
                    <Matches
                        key={discovery.fetched_at}
                        users={discovery.users}
                    />
                ) : (
                    <div
                        className="px-6 py-12 text-center sm:px-12"
                        role="status"
                    >
                        <h2 className="font-semibold">
                            {discovery.truncated
                                ? 'No matches found among the GitHub follows checked.'
                                : 'No public GitHub connections found here yet.'}
                        </h2>
                        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                            People you follow on GitHub need to have joined
                            devnull to appear here.{' '}
                            {discovery.truncated
                                ? 'Explore Everyone to find someone new.'
                                : 'If your GitHub profile is private, your following list may be hidden. You can check again later or explore Everyone to find someone new.'}
                        </p>
                        <Button variant="outline" className="mt-5" asChild>
                            <Link href={LogController.show('everyone')}>
                                Explore Everyone
                            </Link>
                        </Button>
                    </div>
                ))}
        </>
    );
}

function Matches({ users }: { users: Connection[] }) {
    const form = useForm<{ user_ids: string[] }>({ user_ids: [] });
    const available = users.filter((user) => !user.is_followed);
    const selectedIds = form.data.user_ids.filter((id) =>
        available.some((user) => user.id === id),
    );
    const allSelected =
        available.length > 0 &&
        available.slice(0, 100).every((user) => selectedIds.includes(user.id));
    const error = Object.values(form.errors)[0];

    const select = (id: string, checked: boolean) => {
        form.clearErrors();
        form.setData(
            'user_ids',
            checked
                ? [...selectedIds, id]
                : selectedIds.filter((selected) => selected !== id),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform(() => ({ user_ids: selectedIds }));
        form.submit(followSelected(), {
            only: ['auth', 'discovery'],
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                router.flushAll();
            },
        });
    };

    return (
        <form onSubmit={submit}>
            <div className="border-b px-4 py-4 sm:px-6" aria-live="polite">
                <h2 className="font-semibold">
                    {users.length}{' '}
                    {users.length === 1 ? 'familiar face' : 'familiar faces'} on
                    devnull
                </h2>
                {available.length === 0 && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        You're already following everyone here.
                    </p>
                )}
            </div>

            {available.length > 0 && (
                <div className="flex flex-wrap items-center gap-3 border-b bg-muted/30 px-4 py-3 sm:px-6">
                    <Checkbox
                        id="select-github-people"
                        checked={
                            allSelected
                                ? true
                                : selectedIds.length > 0
                                  ? 'indeterminate'
                                  : false
                        }
                        disabled={form.processing}
                        onCheckedChange={(checked) => {
                            form.clearErrors();
                            form.setData(
                                'user_ids',
                                checked === true
                                    ? available
                                          .slice(0, 100)
                                          .map((user) => user.id)
                                    : [],
                            );
                        }}
                    />
                    <label htmlFor="select-github-people" className="text-sm">
                        {available.length > 100
                            ? 'Select first 100'
                            : 'Select all'}
                    </label>
                    {selectedIds.length > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={form.processing}
                            onClick={() => form.setData('user_ids', [])}
                        >
                            Clear
                        </Button>
                    )}
                    <Button
                        type="submit"
                        size="sm"
                        className="ml-auto"
                        disabled={form.processing || selectedIds.length === 0}
                    >
                        {form.processing && <Spinner />}
                        {form.processing
                            ? 'Following…'
                            : selectedIds.length > 0
                              ? `Follow ${selectedIds.length} selected`
                              : 'Follow selected'}
                    </Button>
                    {selectedIds.length === 100 && available.length > 100 && (
                        <p className="w-full text-xs text-muted-foreground">
                            You can follow up to 100 people at a time.
                        </p>
                    )}
                    <InputError message={error} className="w-full" />
                </div>
            )}

            <ul className="divide-y">
                {users.map((person) => (
                    <li
                        key={person.id}
                        className="flex items-start gap-3 px-4 py-4 sm:px-6"
                    >
                        {!person.is_followed && (
                            <Checkbox
                                checked={selectedIds.includes(person.id)}
                                disabled={
                                    form.processing ||
                                    (selectedIds.length >= 100 &&
                                        !selectedIds.includes(person.id))
                                }
                                onCheckedChange={(checked) =>
                                    select(person.id, checked === true)
                                }
                                aria-label={`Select @${person.username}`}
                                className="mt-3"
                            />
                        )}
                        <Link
                            href={show(person)}
                            className="group flex min-w-0 flex-1 items-start gap-3"
                        >
                            <UserAvatar
                                user={person}
                                className="size-10 shrink-0"
                            />
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold group-hover:underline">
                                    {person.name}
                                </p>
                                <p className="truncate font-mono text-[13px] text-muted-foreground">
                                    @{person.username}
                                </p>
                                {person.bio && (
                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                        {person.bio}
                                    </p>
                                )}
                            </div>
                        </Link>
                        <FollowButton
                            user={person}
                            isFollowing={person.is_followed}
                            size="sm"
                            className="min-w-24 shrink-0"
                            only={['auth', 'discovery']}
                            preserveState
                            disabled={form.processing}
                            onSuccess={() => router.flushAll()}
                        />
                    </li>
                ))}
            </ul>
        </form>
    );
}

Index.layout = AppLayout;
