import { Head, router, usePage } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { useState } from 'react';
import PendingFollowController from '@/actions/App/Http/Controllers/PendingFollowController';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { BioText } from '@/components/bio-text';
import { useFollowHover } from '@/components/follow-button';
import { GitHubIcon } from '@/components/github-icon';
import { ProfileShareButton } from '@/components/profile-share-button';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { toastWhenThrottled } from '@/lib/throttle';
import { login } from '@/routes';
import type { User } from '@/types';

type UnclaimedProfile = {
    id: string;
    handle: string;
    name: string;
    avatar_url: string | null;
    bio: string | null;
};

/**
 * A developer looked up from GitHub who isn't on /dev/null yet. Signing in as them makes the page theirs. Where they
 * are is left out, even when GitHub shows it: they never chose to share it here.
 */
export default function UnclaimedProfile({
    profile,
    waitingCount,
    isWaiting,
    waitingYouFollow,
}: {
    profile: UnclaimedProfile;
    waitingCount: number;
    isWaiting: boolean;
    waitingYouFollow: Pick<User, 'id' | 'handle' | 'name' | 'avatar_url'>[];
}) {
    const { auth } = usePage().props;
    const [isWaitingNow, setIsWaitingNow] = useState(isWaiting);
    const shownWaitingCount =
        waitingCount + Number(isWaitingNow) - Number(isWaiting);

    return (
        <>
            <Head title={`${profile.name} (@${profile.handle})`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <p className="min-w-0 truncate font-semibold">{profile.name}</p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    not here yet
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="rounded-full border-2 border-dashed border-rail p-1">
                        <UserAvatar
                            user={profile}
                            className="size-18 text-2xl"
                            pixels={160}
                        />
                    </div>

                    <div className="flex min-w-0 flex-wrap justify-end gap-1.5 sm:gap-2">
                        <ProfileShareButton
                            handle={profile.handle}
                            href={show(profile.handle).url}
                        />
                        {auth.user === null ? (
                            <Button asChild>
                                <a href={login().url}>
                                    <GitHubIcon className="size-4" />
                                    {`I am @${profile.handle}`}
                                </a>
                            </Button>
                        ) : (
                            <WaitButton
                                profile={profile}
                                isWaiting={isWaitingNow}
                                onToggle={setIsWaitingNow}
                            />
                        )}
                    </div>
                </div>

                <h1 className="mt-4 text-2xl font-semibold tracking-tight text-balance">
                    {profile.name}
                </h1>
                <p className="flex flex-wrap items-center gap-x-1.5 gap-y-1 font-mono text-sm text-muted-foreground">
                    @{profile.handle}
                    <span className="ml-1 shrink-0 rounded border border-dashed border-rail px-1.5 py-px font-sans text-xs">
                        Not on /dev/null yet
                    </span>
                </p>

                {profile.bio && (
                    <p className="mt-3 max-w-prose text-[15px] leading-relaxed wrap-break-word whitespace-pre-wrap">
                        <BioText bio={profile.bio} />
                    </p>
                )}

                <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                    <li className="flex items-center gap-1.5">
                        <GitHubIcon className="size-4" />
                        <a
                            href={`https://github.com/${profile.handle}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary hover:underline"
                        >
                            github.com/{profile.handle}
                        </a>
                    </li>
                </ul>

                {shownWaitingCount > 0 && (
                    <div className="mt-4 flex items-start gap-2.5 text-sm">
                        {waitingYouFollow.length > 0 && (
                            <span className="flex shrink-0 pt-px">
                                {waitingYouFollow.map((user) => (
                                    <UserAvatar
                                        key={user.id}
                                        user={user}
                                        className="-ml-2 size-5 ring-2 ring-card first:ml-0"
                                        pixels={40}
                                    />
                                ))}
                            </span>
                        )}
                        <div className="min-w-0">
                            <p>
                                <span
                                    className="font-mono font-medium tabular-nums"
                                    title={exactCountTitle(shownWaitingCount)}
                                >
                                    {formatCount(shownWaitingCount)}
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    {shownWaitingCount === 1
                                        ? 'member waiting to follow'
                                        : 'members waiting to follow'}
                                    {waitingYouFollow.length > 0 &&
                                        `, including ${new Intl.ListFormat('en').format(waitingYouFollow.map((user) => `@${user.handle}`))}`}
                                </span>
                            </p>
                            <p className="mt-0.5 text-muted-foreground">
                                They'll follow as soon as @{profile.handle}{' '}
                                signs up.
                            </p>
                        </div>
                    </div>
                )}
            </header>

            <div className="min-h-dvh px-4 py-10 text-center sm:px-6">
                <p className="font-mono text-sm text-muted-foreground">
                    $ git log --author={profile.handle}
                </p>
                <p className="mt-1.5 text-[15px] text-pretty text-muted-foreground">
                    @{profile.handle} hasn't committed here yet. When they sign
                    in with GitHub, this page becomes theirs.
                </p>
            </div>
        </>
    );
}

UnclaimedProfile.layout = AppLayout;

/**
 * Following a developer who isn't here yet waits for them to sign up. It shows at once, and comes back if the server
 * refuses it. Right after a click, it never turns into cancelling under the pointer.
 */
function WaitButton({
    profile,
    isWaiting,
    onToggle,
}: {
    profile: UnclaimedProfile;
    isWaiting: boolean;
    onToggle: (isWaiting: boolean) => void;
}) {
    const { isJustToggled, markToggled, onMouseLeave } = useFollowHover();
    // Each click waits for the one before it to answer, so a double click can't follow and then stop.
    const [isPending, setIsPending] = useState(false);

    const toggle = () => {
        if (isPending) {
            return;
        }

        const waits = !isWaiting;

        markToggled();
        onToggle(waits);
        router.visit(
            waits
                ? PendingFollowController.store(profile)
                : PendingFollowController.destroy(profile),
            {
                only: ['waitingCount', 'isWaiting', 'waitingYouFollow'],
                preserveState: true,
                preserveScroll: true,
                async: true,
                onStart: () => setIsPending(true),
                onFinish: () => setIsPending(false),
                onHttpException: (response) => {
                    onToggle(!waits);

                    // Signed up meanwhile: the page is theirs now.
                    if (response.status === 404) {
                        router.reload();

                        return false;
                    }

                    return !toastWhenThrottled(response);
                },
                onNetworkError: () => onToggle(!waits),
            },
        );
    };

    return (
        <Button
            variant={isWaiting ? 'outline' : 'default'}
            className="group/follow min-w-36"
            aria-label={
                isWaiting
                    ? `Stop waiting to follow @${profile.handle}`
                    : `Follow @${profile.handle} once they sign up`
            }
            onClick={toggle}
            onMouseLeave={onMouseLeave}
        >
            {!isWaiting ? (
                'Follow'
            ) : isJustToggled ? (
                <>
                    <Clock />
                    Waiting
                </>
            ) : (
                <>
                    <span className="flex items-center gap-2 group-hover/follow:hidden">
                        <Clock />
                        Waiting
                    </span>
                    <span className="hidden text-destructive group-hover/follow:inline">
                        Stop waiting
                    </span>
                </>
            )}
        </Button>
    );
}
