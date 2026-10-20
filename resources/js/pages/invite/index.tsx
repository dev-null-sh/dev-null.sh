import { Deferred, Head, usePage } from '@inertiajs/react';
import { Share2 } from 'lucide-react';
import { toast } from 'sonner';
import { EmptyFeed } from '@/components/commit-feed';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { UserAvatar } from '@/components/user-avatar';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import { shareProfile } from '@/lib/share-profile';
import { show } from '@/routes/user';

export type Invitee = { id: string; login: string; avatar_url: string };

export function InviteButton({ invitee }: { invitee: Invitee }) {
    const { auth } = usePage().props;
    const [, copy] = useClipboard();

    if (auth.user === null) {
        return null;
    }

    const onInvite = async () => {
        const result = await shareProfile(
            {
                title: 'Join me on /dev/null',
                text: `Hey @${invitee.login}, I'm on /dev/null, a social network for developers. Join me:`,
                url: new URL(show(auth.user!).url, window.location.origin).href,
            },
            navigator.share ? (data) => navigator.share(data) : undefined,
            copy,
        );

        if (result === 'copied') {
            toast(`Invite copied, send it to @${invitee.login}`);
        } else if (result === 'failed') {
            toast.error('Could not copy the invite');
        }
    };

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onInvite}
            aria-label={`Invite @${invitee.login}`}
            className="shrink-0"
            data-test="invite-button"
        >
            <Share2 />
            Invite
        </Button>
    );
}

function Invitees({ invitees }: { invitees: Invitee[] | null }) {
    if (invitees === null) {
        return (
            <div className="px-6 py-16 text-center sm:px-12">
                <EmptyFeed command="fatal: unable to reach github.com">
                    GitHub didn't answer. Try again in a minute.
                </EmptyFeed>
            </div>
        );
    }

    if (invitees.length === 0) {
        return (
            <div className="px-6 py-16 text-center sm:px-12">
                <EmptyFeed command="Everything up-to-date">
                    Everyone you follow on GitHub is already on /dev/null.
                </EmptyFeed>
            </div>
        );
    }

    return (
        <ul className="divide-y">
            {invitees.map((invitee) => (
                <li
                    key={invitee.id}
                    className="flex items-center gap-3 px-4 py-3 sm:px-6"
                >
                    <a
                        href={`https://github.com/${invitee.login}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex min-w-0 flex-1 items-center gap-3 hover:underline"
                    >
                        <UserAvatar
                            user={{
                                name: invitee.login,
                                avatar_url: invitee.avatar_url,
                            }}
                            className="size-10"
                        />
                        <span className="truncate font-mono text-sm">
                            @{invitee.login}
                        </span>
                    </a>
                    <InviteButton invitee={invitee} />
                </li>
            ))}
        </ul>
    );
}

function InviteesSkeleton() {
    return (
        <ul className="divide-y" aria-busy>
            {Array.from({ length: 6 }, (_, index) => (
                <li
                    key={index}
                    className="flex items-center gap-3 px-4 py-3 sm:px-6"
                >
                    <Skeleton className="size-10 rounded-full" />
                    <Skeleton className="h-4 w-32" />
                </li>
            ))}
        </ul>
    );
}

export default function Index({ invitees }: { invitees?: Invitee[] | null }) {
    return (
        <>
            <Head title="Invite from GitHub" />

            <header className="sticky top-0 z-10 flex h-14 items-center border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Invite from GitHub</h1>
            </header>

            <p className="border-b px-4 py-3 text-sm text-muted-foreground sm:px-6">
                People you follow on GitHub who aren't on /dev/null yet. Invite
                shares a link to your profile, and you follow them here as soon
                as they join.
            </p>

            <Deferred data="invitees" fallback={<InviteesSkeleton />}>
                <Invitees invitees={invitees ?? null} />
            </Deferred>
        </>
    );
}

Index.layout = AppLayout;
