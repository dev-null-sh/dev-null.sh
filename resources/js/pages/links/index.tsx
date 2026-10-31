import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Link2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import CommitLinkPreviewController from '@/actions/App/Http/Controllers/CommitLinkPreviewController';
import LinkController from '@/actions/App/Http/Controllers/LinkController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { HiddenLinkPreviewContext } from '@/components/hidden-link-preview-context';
import { LinkPreview } from '@/components/link-preview';
import { useDateTime } from '@/hooks/use-date-time';
import AppLayout from '@/layouts/app-layout';
import { hostname } from '@/lib/urls';
import type {
    Commit,
    CursorPaginated,
    LinkPreview as LinkPreviewData,
} from '@/types';

type TopLink = {
    url: string;
    title: string | null;
    site_name: string | null;
    people: number;
    commits: number;
    last_mentioned_at: string;
};

type Props =
    | { url: null; topLinks: TopLink[] }
    | {
          url: string;
          link: LinkPreviewData | null;
          stats: { commits: number; people: number };
          commits: CursorPaginated<Commit> | null;
      };

function commitCount(count: number) {
    return `${count} commit${count === 1 ? '' : 's'}`;
}

function peopleCount(count: number) {
    return count === 1 ? '1 person' : `${count} people`;
}

export default function Index(props: Props) {
    const [draft, setDraft] = useState(props.url ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const url = draft.trim();
        router.get(LinkController.index({ query: url === '' ? {} : { url } }));
    };

    return (
        <>
            <Head title="Links" />

            <header className="sticky top-0 z-10 border-b bg-card px-4 py-3 sm:px-6">
                <h1 className="sr-only">Links</h1>
                <form onSubmit={submit} className="relative">
                    <Link2
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        type="url"
                        inputMode="url"
                        autoFocus={props.url === null}
                        value={draft}
                        onChange={(event) => setDraft(event.target.value)}
                        placeholder="Paste a link to see every commit about it"
                        aria-label="Link"
                        autoComplete="off"
                        className="h-11 w-full rounded-lg border bg-background pr-4 pl-10 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring md:text-[15px]"
                    />
                </form>
            </header>

            {props.url === null ? (
                <TopLinks links={props.topLinks} />
            ) : (
                <LinkCommits
                    url={props.url}
                    link={props.link}
                    stats={props.stats}
                    commits={props.commits}
                />
            )}
        </>
    );
}

function TopLinks({ links }: { links: TopLink[] }) {
    const { formatRelativeTime } = useDateTime();
    return (
        <>
            <p className="border-b px-4 py-3 text-sm text-muted-foreground sm:px-6">
                Most shared links in the last 30 days, by people who used them
                as their link card.
            </p>

            {links.length === 0 ? (
                <div className="px-4 py-12 text-center sm:px-6">
                    <EmptyFeed command="fatal: no links shared yet">
                        Commits with a link card will show up here.
                    </EmptyFeed>
                </div>
            ) : (
                <ol>
                    {links.map((link, index) => (
                        <li key={link.url} className="border-b">
                            <Link
                                href={LinkController.index({
                                    query: { url: link.url },
                                })}
                                className="flex gap-3 px-4 py-3 transition-colors hover:bg-accent focus-visible:bg-accent sm:px-6"
                            >
                                <span className="w-5 shrink-0 pt-0.5 text-right font-mono text-[13px] text-muted-foreground tabular-nums">
                                    {index + 1}
                                </span>
                                <span className="flex min-w-0 flex-1 flex-col">
                                    <span className="truncate font-mono text-[13px] text-muted-foreground">
                                        {link.site_name ?? hostname(link.url)}
                                    </span>
                                    <span className="line-clamp-2 text-[15px] leading-snug font-medium wrap-break-word">
                                        {link.title ?? link.url}
                                    </span>
                                    <span className="mt-1 text-sm text-muted-foreground">
                                        {peopleCount(link.people)} ·{' '}
                                        {commitCount(link.commits)} · last{' '}
                                        {formatRelativeTime(
                                            link.last_mentioned_at,
                                        )}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ol>
            )}
        </>
    );
}

function LinkCommits({
    url,
    link,
    stats,
    commits,
}: {
    url: string;
    link: LinkPreviewData | null;
    stats: { commits: number; people: number };
    commits: CursorPaginated<Commit> | null;
}) {
    const firstCommit = commits?.data[0];

    return (
        <>
            <div className="border-b px-4 py-3 sm:px-6">
                <Link
                    href={LinkController.index()}
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft aria-hidden className="size-3.5" />
                    Most shared links
                </Link>

                {link && firstCommit ? (
                    <LinkPreview
                        refreshUrl={CommitLinkPreviewController.show.url(
                            firstCommit.id,
                        )}
                        preview={link}
                    />
                ) : null}

                <a
                    href={url}
                    target="_blank"
                    rel="noopener noreferrer nofollow ugc"
                    className="mt-2 block truncate font-mono text-[13px] text-muted-foreground hover:text-foreground"
                >
                    {url}
                </a>

                {stats.commits > 0 && (
                    <p className="mt-1 text-sm font-medium">
                        {commitCount(stats.commits)} by{' '}
                        {peopleCount(stats.people)}, oldest first
                    </p>
                )}
            </div>

            {commits === null ? (
                <div className="px-4 py-12 text-center sm:px-6">
                    <EmptyFeed command="fatal: no commits about this link">
                        No commit uses this exact link as its card yet.
                    </EmptyFeed>
                </div>
            ) : (
                <HiddenLinkPreviewContext value={link?.url ?? null}>
                    <CommitFeed
                        commits={commits}
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
        </>
    );
}

Index.layout = AppLayout;
