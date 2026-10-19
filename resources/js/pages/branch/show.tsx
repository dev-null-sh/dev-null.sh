import { Form, Head, Link, usePage } from '@inertiajs/react';
import { BadgeCheck, Eye, EyeOff, GitBranch } from 'lucide-react';
import { useState } from 'react';
import BranchController from '@/actions/App/Http/Controllers/BranchController';
import BranchWatchController from '@/actions/App/Http/Controllers/BranchWatchController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { PageTabs } from '@/components/page-tabs';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { UserPreview } from '@/components/user-preview';
import { VerifiedDomain } from '@/components/verified-domain';
import AppLayout from '@/layouts/app-layout';
import { branchTextColor } from '@/lib/branch-color';
import { cn } from '@/lib/utils';
import { show as userShow } from '@/routes/user';
import type { Commit, CursorPaginated, User } from '@/types';

const branchRoleLabel = {
    maintainer: 'Maintainer',
    contributor: 'Contributor',
} as const;

type BranchMember = {
    role: 'maintainer' | 'contributor';
    user: Pick<User, 'id' | 'username' | 'name' | 'avatar_url' | 'lane'>;
};

type BranchPayload = {
    name: string;
    description: string | null;
    domain_description: string | null;
    brand_color: string | null;
    og_image_url: string | null;
    domain: string | null;
    verified_domain: string | null;
    is_verified: boolean;
    is_owned: boolean;
    can_claim: boolean;
    claim_pending: boolean;
    claim_requested_by_viewer: boolean;
    org_locked: boolean;
    verification_host: string | null;
    verification_record: string | null;
    viewer_role: 'maintainer' | 'contributor' | null;
    is_watching: boolean;
    members: BranchMember[];
};

/**
 * Mark themes from the brand kit. Keep backgrounds in sync with brand/show.
 */
const markThemes = ['light', 'dark', 'black', 'white'] as const;

type MarkTheme = (typeof markThemes)[number];

const markThemeBackgrounds: Record<MarkTheme, string> = {
    light: 'bg-[#f6f7f8]',
    dark: 'bg-[#111112]',
    black: 'bg-white',
    white: 'bg-[#d9480f]',
};

/**
 * Stable pick of a mark theme from the branch name, so the banner does not
 * flicker across Inertia visits or remounts.
 */
function markThemeForBranch(name: string): MarkTheme {
    let hash = 0;

    for (const character of name.toLowerCase()) {
        hash = (hash * 31 + character.charCodeAt(0)) >>> 0;
    }

    return markThemes[hash % markThemes.length];
}

function BranchBanner({
    name,
    ogImageUrl,
}: {
    name: string;
    ogImageUrl: string | null;
}) {
    if (ogImageUrl) {
        return (
            <div className="aspect-[2.4/1] w-full overflow-hidden border-b bg-muted">
                <img
                    src={ogImageUrl}
                    alt=""
                    className="size-full object-cover"
                />
            </div>
        );
    }

    const theme = markThemeForBranch(name);

    return (
        <div
            className={cn(
                'flex aspect-[2.4/1] w-full items-center justify-center overflow-hidden border-b',
                markThemeBackgrounds[theme],
            )}
            aria-hidden
        >
            <img
                src={`/images/brand/dev-null-mark-${theme}.svg`}
                alt=""
                className="h-[42%] max-h-28 w-auto max-w-[38%]"
            />
        </div>
    );
}

export default function Show({
    branch,
    sort,
    isRanked,
    commits,
    stats,
}: {
    branch: BranchPayload;
    sort: 'latest' | 'trending';
    isRanked: boolean;
    commits: CursorPaginated<Commit>;
    stats: { commits: number; authors: number };
}) {
    const { auth, errors: pageErrors } = usePage().props;
    const user = auth.user;
        user !== null &&
        !branch.is_owned &&
        branch.claim_pending &&
        !branch.claim_requested_by_viewer;
    const showOrgLocked =
        user !== null &&
        !branch.is_owned &&
        !branch.can_claim &&
        !branch.claim_pending &&
        branch.org_locked;

    return (
        <>
            <Head title={branch.name} />

            <BranchBanner
                name={branch.name}
                ogImageUrl={branch.og_image_url}
            />

            <header className="sticky top-0 z-10 border-b bg-card">
                <div className="space-y-2 px-4 py-3 sm:px-6">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 space-y-1">
                            <h1
                                className="flex min-w-0 items-center gap-1.5 truncate font-semibold"
                                style={
                                    branch.brand_color
                                        ? {
                                            color: branchTextColor(
                                                branch.brand_color,
                                            ),
                                        }
                                        : undefined
                                }
                            >
                                <GitBranch
                                    className="size-4 shrink-0"
                                    aria-hidden
                                />
                                <span className="truncate">{branch.name}</span>
                            </h1>
                            {branch.is_verified && branch.verified_domain && (
                                <VerifiedDomain
                                    domain={branch.verified_domain}
                                />
                            )}
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {user !== null && (
                                <Form
                                    {...(branch.is_watching
                                        ? BranchWatchController.destroy.form(
                                            branch.name,
                                        )
                                        : BranchWatchController.store.form(
                                            branch.name,
                                        ))}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                            disabled={processing}
                                            aria-pressed={branch.is_watching}
                                            className="gap-1.5"
                                            data-test="branch-watch-toggle"
                                        >
                                            {branch.is_watching ? (
                                                <EyeOff
                                                    className="size-4"
                                                    strokeWidth={1.75}
                                                />
                                            ) : (
                                                <Eye
                                                    className="size-4"
                                                    strokeWidth={1.75}
                                                />
                                            )}
                                            {branch.is_watching
                                                ? 'Unwatch'
                                                : 'Watch'}
                                        </Button>
                                    )}
                                </Form>
                            )}
                                                                                                                {showOrgLocked && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-flex">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled
                                                className="gap-1.5 border-amber-500/35 text-amber-600 dark:text-amber-400"
                                                data-test="branch-claim-locked"
                                            >
                                                <BadgeCheck
                                                    className="size-4 fill-amber-400/25 text-amber-500 dark:fill-amber-400/20 dark:text-amber-400"
                                                    strokeWidth={1.75}
                                                />
                                                Claim
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent className="max-w-56 text-left">
                                        #{branch.name} matches a Remotes org.
                                        Only that org&apos;s maintainers can
                                        claim it.
                                    </TooltipContent>
                                </Tooltip>
                            )}
                            {branch.viewer_role !== null && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={BranchController.edit(
                                            branch.name,
                                        )}
                                    >
                                        Manage
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                    {(branch.description ||
                        (branch.domain_description &&
                            branch.domain_description !==
                            branch.description) ||
                        stats.commits > 0 ||
                        branch.members.length > 0) && (
                            <div className="space-y-1">
                                {branch.description && (
                                    <p className="text-sm text-foreground">
                                        {branch.description}
                                    </p>
                                )}
                                {branch.domain_description &&
                                    branch.domain_description !==
                                    branch.description && (
                                        <p className="text-sm text-muted-foreground">
                                            {branch.domain_description}
                                        </p>
                                    )}
                                <p className="font-mono text-[13px] text-muted-foreground">
                                    {stats.commits}{' '}
                                    {stats.commits === 1 ? 'commit' : 'commits'} ·{' '}
                                    {stats.authors}{' '}
                                    {stats.authors === 1 ? 'author' : 'authors'}
                                </p>
                                {branch.members.length > 0 && (
                                    <ul
                                        className="flex -space-x-1.5 pt-1"
                                        aria-label="Branch members"
                                    >
                                        {branch.members.map((member, index) => (
                                            <li
                                                key={member.user.id}
                                                className="relative"
                                                style={{
                                                    zIndex:
                                                        branch.members.length -
                                                        index,
                                                }}
                                            >
                                                <UserPreview
                                                    user={member.user}
                                                    badge={
                                                        branchRoleLabel[
                                                        member.role
                                                        ]
                                                    }
                                                    className="block"
                                                >
                                                    <Link
                                                        href={userShow(
                                                            member.user
                                                                .username,
                                                        )}
                                                        className="block rounded-full ring-2 ring-card transition-opacity hover:opacity-90"
                                                    >
                                                        <UserAvatar
                                                            user={member.user}
                                                            className="size-6"
                                                        />
                                                        <span className="sr-only">
                                                            @
                                                            {
                                                                member.user
                                                                    .username
                                                            }{' '}
                                                            ({member.role})
                                                        </span>
                                                    </Link>
                                                </UserPreview>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                </div>
                <PageTabs
                    label="Branch sort"
                    prefetch={false}
                    tabs={[
                        {
                            title: 'Latest',
                            href: BranchController.show(branch.name),
                            isActive: sort === 'latest',
                        },
                        {
                            title: 'Trending',
                            href: BranchController.show.url(branch.name, {
                                query: { sort: 'trending' },
                            }),
                            isActive: sort === 'trending',
                        },
                    ]}
                />
            </header>

            <CommitFeed
                commits={commits}
                isRanked={isRanked}
                end="That's every commit on this branch."
                empty={
                    <EmptyFeed
                        command={`fatal: no commits on branch ${branch.name}`}
                    >
                        Nobody has used #{branch.name} on a top-level commit
                        yet. Write it in a commit to start the log.
                        <Link
                            href={LogController.show('trending')}
                            className="mt-3 block text-primary hover:underline"
                        >
                            Explore Trending
                        </Link>
                    </EmptyFeed>
                }
            />
        </>
    );
}

Show.layout = AppLayout;
