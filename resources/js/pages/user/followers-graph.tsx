import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Network, UserCheck, Users, UserX } from 'lucide-react';
import { useMemo, useState } from 'react';
import UserFollowersController from '@/actions/App/Http/Controllers/UserFollowersController';
import UserFollowersGraphController from '@/actions/App/Http/Controllers/UserFollowersGraphController';
import UserFollowingController from '@/actions/App/Http/Controllers/UserFollowingController';
import { EmptyFeed } from '@/components/commit-feed';
import {
    FollowersNetworkGraph,
    type FollowerGraphEdge,
    type FollowerGraphFilter,
    type FollowerGraphNode,
} from '@/components/followers-network-graph';
import { PageTabs } from '@/components/page-tabs';
import { SegmentedControl } from '@/components/segmented-control';
import AppLayout from '@/layouts/app-layout';
import { show } from '@/routes/user';
import type { Profile } from '@/types';

export default function FollowersGraph({
    user,
    nodes,
    edges,
    totalFollowers,
    limit,
}: {
    user: Profile;
    nodes: FollowerGraphNode[];
    edges: FollowerGraphEdge[];
    totalFollowers: number;
    limit: number;
}) {
    const [filter, setFilter] = useState<FollowerGraphFilter>('everyone');

    const followedBackCount = useMemo(
        () => nodes.filter((node) => node.followed_back).length,
        [nodes],
    );
    const notFollowedBackCount = nodes.length - followedBackCount;
    return (
        <>
            <Head title={`Follower network · @${user.username}`} />

            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center gap-3 border-b px-4 sm:px-6">
                    <Link
                        href={show(user)}
                        aria-label={`Back to @${user.username}`}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                    </Link>
                    <h1 className="min-w-0 truncate font-semibold">
                        {user.name}
                    </h1>
                    <p className="shrink-0 font-mono text-[13px] text-muted-foreground">
                        @{user.username}
                    </p>
                </div>
                <PageTabs
                    label="Connections"
                    tabs={[
                        {
                            title: 'Followers',
                            href: UserFollowersController.index(user),
                            isActive: false,
                        },
                        {
                            title: 'Following',
                            href: UserFollowingController.index(user),
                            isActive: false,
                        },
                        {
                            title: 'Graph',
                            href: UserFollowersGraphController.show(user),
                            isActive: true,
                        },
                    ]}
                />
            </div>

            {totalFollowers === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="fatal: no followers yet">
                        Nobody is following @{user.username} yet.
                    </EmptyFeed>
                </div>
            ) : (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-6">
                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                            <Network className="size-4" aria-hidden />
                            <p>
                                <span className="font-mono font-medium text-foreground">
                                    {nodes.length}
                                </span>{' '}
                                {nodes.length === 1 ? 'follower' : 'followers'}{' '}
                                around{' '}
                                <span className="font-mono">
                                    @{user.username}
                                </span>
                                {' · '}
                                <span className="font-mono font-medium text-foreground">
                                    {edges.length}
                                </span>{' '}
                                {edges.length === 1
                                    ? 'connection'
                                    : 'connections'}{' '}
                                between them
                                {totalFollowers > nodes.length && (
                                    <>
                                        {' · showing the most recent '}
                                        <span className="font-mono">
                                            {limit}
                                        </span>
                                        {' of '}
                                        <span className="font-mono">
                                            {totalFollowers}
                                        </span>
                                    </>
                                )}
                            </p>
                        </div>
                        <SegmentedControl<FollowerGraphFilter>
                            label="Filter followers"
                            name="follower-filter"
                            value={filter}
                            onChange={setFilter}
                            options={[
                                {
                                    value: 'everyone',
                                    icon: Users,
                                    label: `Everyone · ${nodes.length}`,
                                },
                                {
                                    value: 'followed-back',
                                    icon: UserCheck,
                                    label: `Followed back · ${followedBackCount}`,
                                },
                                {
                                    value: 'not-followed-back',
                                    icon: UserX,
                                    label: `Not followed back · ${notFollowedBackCount}`,
                                },
                            ]}
                        />
                    </div>

                    <FollowersNetworkGraph
                        user={user}
                        nodes={nodes}
                        edges={edges}
                        filter={filter}
                    />

                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t px-4 py-3 text-xs text-muted-foreground sm:px-6">
                        <p className="flex items-center gap-1.5">
                            <span
                                aria-hidden
                                className="inline-block size-2.5 rounded-full border-2 border-primary"
                            />
                            Followed back
                        </p>
                        <p className="flex items-center gap-1.5">
                            <span
                                aria-hidden
                                className="inline-block size-2.5 rounded-full border-2 border-dashed border-muted-foreground"
                            />
                            Not followed back
                        </p>
                        <p>
                            Hover a node to highlight who they follow in this
                            network. Drag to pan, scroll to zoom, click a node
                            to visit their profile.
                        </p>
                    </div>
                </>
            )}
        </>
    );
}

FollowersGraph.layout = AppLayout;
