import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import CommitLinkPreviewController from '@/actions/App/Http/Controllers/CommitLinkPreviewController';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { HiddenLinkPreviewContext } from '@/components/hidden-link-preview-context';
import { LinkPreview } from '@/components/link-preview';
import {
    displayedLink,
    linkLane,
    siteIcons,
    TopicIcon,
} from '@/components/link-topic';
import { PageTabs } from '@/components/page-tabs';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { exactCountTitle, formatCount } from '@/lib/numbers';
import { home } from '@/routes';
import type {
    Commit,
    CursorPaginated,
    LinkPreview as LinkPreviewData,
} from '@/types';

type LinkStats = { commits: number; people: number };

/** The image a page gives for sharing it, as large as sites draw it, leading to the page. */
function SocialCard({ url, imageUrl }: { url: string; imageUrl: string }) {
    const [isBroken, setIsBroken] = useState(false);

    return isBroken ? null : (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            tabIndex={-1}
            className="mt-4 block overflow-hidden rounded-lg border transition-opacity hover:opacity-90"
        >
            <img
                src={imageUrl}
                alt=""
                referrerPolicy="no-referrer"
                onError={() => setIsBroken(true)}
                className="aspect-[1.91/1] w-full bg-muted object-cover"
            />
        </a>
    );
}

function LinkCount({ count, label }: { count: number; label: string }) {
    return (
        <li className="flex gap-1.5">
            <span
                className="font-mono font-medium tabular-nums"
                title={exactCountTitle(count)}
            >
                {formatCount(count)}
            </span>
            <span className="text-muted-foreground">{label}</span>
        </li>
    );
}

/**
 * A link's page, like an organization's: the link at the top, then every commit about it, the most starred or the
 * newest first, each standing on its own in its author's lane, like in a log.
 */
export default function Show({
    url,
    pageUrl,
    link,
    iconUrl,
    sort,
    stats,
    commits,
}: {
    url: string;
    pageUrl: string;
    link: LinkPreviewData | null;
    iconUrl: string | null;
    sort: 'top' | 'latest';
    stats: LinkStats;
    commits: CursorPaginated<Commit> | null;
}) {
    const { auth } = usePage().props;
    const title = link?.title ?? displayedLink(url);
    const lane = linkLane(url);
    const firstCommit = commits?.data[0];

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(auth.log === null ? home() : LogController.show(auth.log));
    };

    return (
        <>
            <Head title={title} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <button
                    type="button"
                    onClick={goBack}
                    aria-label="Back"
                    className="-ml-1.5 shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <ArrowLeft aria-hidden className="size-4" />
                </button>
                <p className="min-w-0 truncate font-semibold">{title}</p>
                <p className="ml-auto shrink-0 font-mono text-[13px] text-muted-foreground">
                    link
                </p>
            </div>

            <header className="border-b px-4 pt-6 pb-5 sm:px-6">
                <div className="flex items-start justify-between gap-4">
                    <span
                        className="flex rounded-lg ring-2 ring-(--lane) ring-offset-4 ring-offset-card"
                        style={{ '--lane': lane } as CSSProperties}
                    >
                        <TopicIcon
                            iconUrls={siteIcons(url, iconUrl)}
                            className="size-16 rounded-lg"
                        />
                    </span>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={url}
                                target="_blank"
                                rel="noopener noreferrer nofollow ugc"
                            >
                                <ExternalLink />
                                Open link
                            </a>
                        </Button>
                    </div>
                </div>

                <h1 className="mt-4 text-2xl font-semibold tracking-tight text-balance wrap-break-word">
                    {title}
                </h1>
                <p className="mt-0.5 flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                    <span
                        aria-hidden
                        className="size-2 shrink-0 rounded-full"
                        style={{ background: lane }}
                    />
                    <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer nofollow ugc"
                        title={url}
                        className="min-w-0 truncate hover:text-foreground hover:underline"
                    >
                        {displayedLink(url)}
                    </a>
                </p>

                {link?.description && (
                    <p className="mt-3 max-w-prose text-[15px] leading-relaxed wrap-break-word">
                        {link.description}
                    </p>
                )}

                {link?.embed && firstCommit ? (
                    <LinkPreview
                        refreshUrl={CommitLinkPreviewController.show.url(
                            firstCommit.id,
                        )}
                        preview={link}
                        source={{ commitId: firstCommit.id }}
                    />
                ) : (
                    link?.image_url && (
                        <SocialCard url={url} imageUrl={link.image_url} />
                    )
                )}

                <ul className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <LinkCount
                        count={stats.commits}
                        label={stats.commits === 1 ? 'commit' : 'commits'}
                    />
                    <LinkCount
                        count={stats.people}
                        label={stats.people === 1 ? 'person' : 'people'}
                    />
                </ul>
            </header>

            <PageTabs
                label="Link feed"
                preserveScroll
                only={['sort', 'commits']}
                reset={['commits']}
                tabs={[
                    {
                        title: 'Top',
                        href: pageUrl,
                        isActive: sort === 'top',
                    },
                    {
                        title: 'Latest',
                        href: `${pageUrl}?sort=latest`,
                        isActive: sort === 'latest',
                    },
                ]}
            />

            <div className="min-h-dvh">
                {commits === null ? (
                    <div className="px-4 py-12 text-center sm:px-6">
                        <EmptyFeed command="fatal: no commits about this link">
                            No commit uses this link as its card yet.
                        </EmptyFeed>
                    </div>
                ) : (
                    <HiddenLinkPreviewContext value={link?.url ?? null}>
                        <CommitFeed
                            commits={commits}
                            isRanked={sort === 'top'}
                            end="That's every commit about this link."
                            empty={
                                <EmptyFeed command="fatal: no commits about this link">
                                    No commit you can see uses this link as its
                                    card.
                                </EmptyFeed>
                            }
                        />
                    </HiddenLinkPreviewContext>
                )}
            </div>
        </>
    );
}

Show.layout = AppLayout;
