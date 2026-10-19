import { Head, usePage } from '@inertiajs/react';
import { MemberStack } from '@/components/chat/org';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { GitHubIcon } from '@/components/github-icon';
import { PageTabs } from '@/components/page-tabs';
import { ProfileShareButton } from '@/components/profile-share-button';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { login } from '@/routes';
import { show } from '@/routes/user';
import type { Commit, CursorPaginated, User } from '@/types';

type Account = Pick<User, 'username' | 'name' | 'avatar_url'>;

/**
 * The profile of a developer who isn't on /dev/null yet, which someone mentioning them gave them. It shows what is
 * waiting for them, so they claim it by signing in.
 */
export default function Unclaimed({
    account,
    waiting,
    commits,
}: {
    account: Account;
    waiting: { count: number; users: User[] };
    commits: CursorPaginated<Commit>;
}) {
    const { auth } = usePage().props;
    const { username, name } = account;

    return (
        <>
            <Head title={`${name} (@${username})`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <p className="min-w-0 truncate font-semibold">{name}</p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    is not on /dev/null yet
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <UserAvatar
                        user={account}
                        className="size-20 text-2xl outline-2 outline-offset-4 outline-rail outline-dashed"
                        pixels={160}
                    />

                    <div className="flex shrink-0 gap-1.5 sm:gap-2">
                        <ProfileShareButton
                            username={username}
                            href={show(username).url}
                        />
                        {auth.user === null && (
                            <Button asChild>
                                <a href={login().url} data-test="claim-profile">
                                    I am @{username}
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                <h1 className="mt-4 text-2xl font-semibold tracking-tight text-balance">
                    {name}
                </h1>
                <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                    <span
                        aria-hidden
                        className="size-2 rounded-full border-[1.5px] border-dashed border-muted-foreground"
                    />
                    @{username}
                    <span className="ml-1 shrink-0 rounded bg-muted px-1.5 py-0.5 font-sans text-xs">
                        Not here yet
                    </span>
                </p>

                <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                    <li className="flex items-center gap-1.5">
                        <GitHubIcon className="size-4" />
                        <a
                            href={`https://github.com/${username}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary hover:underline"
                        >
                            github.com/{username}
                        </a>
                    </li>
                </ul>

                {waiting.count > 0 && (
                    <div
                        className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2.5 rounded-lg border px-4 py-3.5"
                        data-test="waiting-followers"
                    >
                        <MemberStack
                            users={waiting.users}
                            max={waiting.users.length}
                            total={waiting.count}
                            avatarClassName="size-8"
                        />
                        <span className="min-w-0 leading-snug">
                            <span className="block text-[15px]">
                                <span className="font-mono font-medium">
                                    {waiting.count}
                                </span>{' '}
                                {waiting.count === 1
                                    ? 'developer is waiting to follow'
                                    : 'developers are waiting to follow'}
                            </span>
                            <span className="block text-[13px] text-muted-foreground">
                                They’ll follow as soon as @{username} signs in.
                            </span>
                        </span>
                    </div>
                )}
            </header>

            <PageTabs
                label="Profile feed"
                tabs={[
                    { title: 'Mentions', href: show(username), isActive: true },
                ]}
            />

            <CommitFeed
                key={username}
                commits={commits}
                end={`That's the first mention of @${username}.`}
                empty={
                    <EmptyFeed command="fatal: no mentions to show">
                        The commits mentioning @{username} aren't visible to
                        you.
                    </EmptyFeed>
                }
            />
        </>
    );
}

Unclaimed.layout = AppLayout;
