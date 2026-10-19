import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Eye, EyeOff, GitBranch } from 'lucide-react';
import BranchClaimController from '@/actions/App/Http/Controllers/BranchClaimController';
import BranchController from '@/actions/App/Http/Controllers/BranchController';
import BranchWatchController from '@/actions/App/Http/Controllers/BranchWatchController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { PageTabs } from '@/components/page-tabs';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { VerifiedDomain } from '@/components/verified-domain';
import AppLayout from '@/layouts/app-layout';
import { branchTextColor } from '@/lib/branch-color';
import { show as userShow } from '@/routes/user';
import type { Commit, CursorPaginated, User } from '@/types';

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
    org_locked: boolean;
    verification_host: string | null;
    verification_record: string | null;
    viewer_role: 'maintainer' | 'contributor' | null;
    is_watching: boolean;
    members: BranchMember[];
};

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
    const { auth } = usePage().props;
    const user = auth.user;

    return (
        <>
            <Head title={branch.name} />

            {branch.og_image_url && (
                <div className="aspect-[2.4/1] w-full overflow-hidden border-b bg-muted">
                    <img
                        src={branch.og_image_url}
                        alt=""
                        className="size-full object-cover"
                    />
                </div>
            )}

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
                            {branch.viewer_role === 'maintainer' && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={BranchController.edit(branch.name)}>
                                        Manage
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                    {(branch.description ||
                        (branch.domain_description &&
                            branch.domain_description !== branch.description) ||
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
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Link
                                                            href={userShow(
                                                                member.user
                                                                    .username,
                                                            )}
                                                            className="block rounded-full ring-2 ring-card transition-opacity hover:opacity-90"
                                                        >
                                                            <UserAvatar
                                                                user={
                                                                    member.user
                                                                }
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
                                                    </TooltipTrigger>
                                                    <TooltipContent className="font-mono text-[12px]">
                                                        @{member.user.username}
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            · {member.role}
                                                        </span>
                                                    </TooltipContent>
                                                </Tooltip>
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

            {user !== null && !branch.is_owned && branch.can_claim && (
                <Form
                    {...BranchClaimController.store.form(branch.name)}
                    className="space-y-3 border-b px-4 py-3 sm:px-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                Claim #{branch.name} for your project by proving a
                                domain with a DNS TXT record.
                            </p>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <input
                                    name="domain"
                                    placeholder="laravel.com"
                                    required
                                    className="h-9 rounded-md border bg-transparent px-3 font-mono text-sm sm:max-w-xs"
                                />
                                <Button type="submit" disabled={processing}>
                                    Claim
                                </Button>
                            </div>
                            {(errors.domain || errors.branch) && (
                                <p className="text-sm text-destructive">
                                    {errors.domain ?? errors.branch}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            )}

            {user !== null && !branch.is_owned && !branch.can_claim && branch.org_locked && (
                <p className="border-b px-4 py-3 text-sm text-muted-foreground sm:px-6">
                    #{branch.name} matches a Remotes org. Only that org&apos;s
                    maintainers can claim and verify this branch.
                </p>
            )}

            <CommitFeed
                commits={commits}
                isRanked={isRanked}
                end="That's every commit on this branch."
                empty={
                    <EmptyFeed command={`fatal: no commits on branch ${branch.name}`}>
                        Nobody has used #{branch.name} on a top-level commit yet.
                        Write it in a commit to start the log.
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
