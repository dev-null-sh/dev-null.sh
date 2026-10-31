import { Head, Link, usePage } from '@inertiajs/react';
import { AlertCircleIcon, Link2, MapPin, Settings } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { BioText } from '@/components/bio-text';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { GitHubIcon } from '@/components/github-icon';
import { OrganizationAvatar } from '@/components/organization-avatar';
import { OrganizationFollowButton } from '@/components/organization-follow-button';
import { PageTabs } from '@/components/page-tabs';
import { ProfileShareButton } from '@/components/profile-share-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { organizationLaneColor } from '@/lib/utils';
import type {
    Commit,
    CursorPaginated,
    Lane,
    Organization,
    User,
} from '@/types';

export default function Show({
    organization,
    lane,
    tab,
    commits,
    claimUrl,
    settingsUrl,
    admins,
    followersCount,
    isFollowing,
}: {
    organization: Organization & { id: string };
    lane: Lane | null;
    tab: 'mentions';
    commits: CursorPaginated<Commit>;
    claimUrl: string;
    settingsUrl: string | null;
    admins: User[];
    followersCount: number;
    isFollowing: boolean;
}) {
    const { props, flash } = usePage();
    const { auth } = props;
    // Kept until the user leaves the page, as the background reloads bring no flash and would hide it. Claiming
    // redirects back here, so its links don't preserve state, or this would keep the value from before the claim.
    const [claimFailed] = useState(flash.claimFailed);
    // Only the first claim makes an admin. While a claim failed, its alert offers to try again instead.
    const claimable = auth.user !== null && admins.length === 0;
    const canClaim = claimable && claimFailed === undefined;

    return (
        <>
            <Head title={`${organization.name} (@${organization.handle})`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <p className="min-w-0 truncate font-semibold">
                    {organization.name}
                </p>
                <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                    organization
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <OrganizationAvatar
                        organization={organization}
                        className="size-20 rounded-lg text-2xl ring-2 ring-(--lane) ring-offset-4 ring-offset-card"
                        pixels={160}
                        style={
                            {
                                '--lane': organizationLaneColor(lane),
                            } as CSSProperties
                        }
                    />

                    <div className="flex min-w-0 flex-wrap justify-end gap-1.5 sm:gap-2">
                        {canClaim && (
                            <Button variant="outline" asChild>
                                {/* A form, so no other site can claim for the user. */}
                                <Link
                                    href={claimUrl}
                                    method="post"
                                    as="button"
                                    preserveState={false}
                                >
                                    Claim
                                </Link>
                            </Button>
                        )}
                        {settingsUrl && (
                            <Button variant="outline" asChild>
                                <Link href={settingsUrl}>
                                    <Settings />
                                    Settings
                                </Link>
                            </Button>
                        )}
                        <ProfileShareButton
                            handle={organization.handle}
                            href={show(organization).url}
                        />
                        <OrganizationFollowButton
                            organization={organization}
                            isFollowing={isFollowing}
                            menu
                            only={['auth', 'followersCount', 'isFollowing']}
                            className="min-w-32"
                        />
                    </div>
                </div>

                <h1 className="mt-4 text-2xl font-semibold tracking-tight text-balance">
                    {organization.name}
                </h1>
                <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                    <span
                        aria-hidden
                        className="size-2 rounded-full"
                        style={{ background: organizationLaneColor(lane) }}
                    />
                    @{organization.handle}
                    <span className="ml-1 shrink-0 rounded bg-muted px-1.5 py-0.5 font-sans text-xs">
                        Organization
                    </span>
                </p>

                {organization.bio && (
                    <p className="mt-3 max-w-prose text-[15px] leading-relaxed wrap-break-word whitespace-pre-wrap">
                        <BioText bio={organization.bio} />
                    </p>
                )}

                <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                    {organization.location && (
                        <li className="flex items-center gap-1.5">
                            <MapPin className="size-4" />
                            {organization.location}
                        </li>
                    )}
                    {organization.website && (
                        <li className="flex min-w-0 items-center gap-1.5">
                            <Link2 className="size-4 shrink-0" />
                            <a
                                href={organization.website}
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                className="truncate text-primary hover:underline"
                            >
                                {organization.website.replace(
                                    /^https?:\/\//u,
                                    '',
                                )}
                            </a>
                        </li>
                    )}
                    <li className="flex items-center gap-1.5">
                        <GitHubIcon className="size-4" />
                        <a
                            href={`https://github.com/${organization.handle}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary hover:underline"
                        >
                            github.com/{organization.handle}
                        </a>
                    </li>
                </ul>

                <p className="mt-4 flex gap-1.5 text-sm">
                    <span
                        className="font-mono font-medium tabular-nums"
                        title={exactCountTitle(followersCount)}
                    >
                        {formatCount(followersCount)}
                    </span>
                    <span className="text-muted-foreground">
                        {followersCount === 1 ? 'follower' : 'followers'}
                    </span>
                </p>

                {admins.length > 0 && (
                    <div className="mt-4 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span>Admins</span>
                        {admins.map((admin) => (
                            <Link
                                key={admin.id}
                                href={show(admin.handle)}
                                className="flex items-center gap-1.5 text-foreground hover:underline"
                            >
                                <UserAvatar
                                    user={admin}
                                    className="size-5"
                                    pixels={40}
                                />
                                @{admin.handle}
                            </Link>
                        ))}
                    </div>
                )}

                {claimFailed && (
                    <Alert className="mt-4 border-destructive-foreground/40 [&>svg]:text-destructive-foreground">
                        <AlertCircleIcon />
                        <AlertTitle className="text-destructive-foreground">
                            Couldn't claim @{organization.handle}
                        </AlertTitle>
                        {/* The reason in the text color, as red at this size is hard to read. */}
                        <AlertDescription className="text-foreground">
                            <p>{claimFailed.message}</p>
                            {(claimFailed.githubUrl || claimable) && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {claimFailed.githubUrl && (
                                        <Button size="sm" asChild>
                                            <a
                                                href={claimFailed.githubUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <GitHubIcon className="size-4" />
                                                Open GitHub
                                            </a>
                                        </Button>
                                    )}
                                    {claimable && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={claimUrl}
                                                method="post"
                                                as="button"
                                                preserveState={false}
                                            >
                                                Try again
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            )}
                        </AlertDescription>
                    </Alert>
                )}
            </header>

            <div className="min-h-dvh">
                <PageTabs
                    label="Organization feed"
                    preserveScroll
                    only={['tab', 'commits']}
                    reset={['commits']}
                    tabs={[
                        {
                            title: 'Mentions',
                            href: show(organization),
                            isActive: tab === 'mentions',
                        },
                    ]}
                />

                <CommitFeed
                    key={`${organization.handle}:${tab}`}
                    commits={commits}
                    end={`That's the first commit mentioning @${organization.handle}.`}
                    empty={
                        <EmptyFeed
                            command={`$ git log --grep=@${organization.handle}`}
                        >
                            Nobody has mentioned @{organization.handle} yet.
                            Mention it in a commit and it shows up here.
                        </EmptyFeed>
                    }
                />
            </div>
        </>
    );
}

Show.layout = AppLayout;
