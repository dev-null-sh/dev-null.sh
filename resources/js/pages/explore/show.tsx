import { Head, Link } from '@inertiajs/react';
import { BookOpen, ChevronDown } from 'lucide-react';
import ExploreController from '@/actions/App/Http/Controllers/ExploreController';
import { CommitFeed } from '@/components/commit-feed';
import {
    ExploreFeedback,
    ExploreTopicCard,
} from '@/components/explore-topic-card';
import { PageTabs } from '@/components/page-tabs';
import AppLayout from '@/layouts/app-layout';
import type { Commit, CursorPaginated } from '@/types';
import type { ExploreReasons, ExploreTopic } from '@/types/explore';

export default function Show({
    topic,
    order,
    related,
    commits,
    reasons,
}: {
    topic: ExploreTopic;
    order: 'top' | 'latest' | 'related';
    related: ExploreTopic[];
    commits: CursorPaginated<Commit> | null;
    reasons: ExploreReasons;
}) {
    return (
        <>
            <Head title={topic.title} />
            <header className="border-b px-4 py-4 sm:px-6">
                <Link
                    href={ExploreController.index()}
                    className="text-sm text-primary hover:underline"
                >
                    ← Explore
                </Link>
                <div className="mt-3 flex items-start gap-3">
                    <h1 className="min-w-0 flex-1 text-xl font-semibold break-words">
                        {topic.kind === 'subject' ? '#' : ''}
                        {topic.title}
                    </h1>
                    <ExploreFeedback topic={topic} reasons={reasons} />
                </div>
                <p className="mt-2 text-sm text-muted-foreground tabular-nums">
                    {topic.commits_count} commits · {topic.contributors_count}{' '}
                    developers
                </p>
                {topic.subjects.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-3">
                        {topic.subjects.map((subject) => (
                            <Link
                                key={subject.id}
                                href={subject.url}
                                className="text-sm text-primary hover:underline"
                            >
                                #{subject.title}
                            </Link>
                        ))}
                    </div>
                )}
                {topic.summary !== null && topic.summary !== '' && (
                    <section aria-label="Community summary" className="mt-4">
                        <p className="text-sm leading-relaxed whitespace-pre-line">
                            {topic.summary}
                        </p>
                        {(topic.sources?.length ?? 0) > 0 && (
                            <details key={topic.id} className="group mt-2">
                                <summary className="flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-sm text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring [&::-webkit-details-marker]:hidden">
                                    <BookOpen aria-hidden className="size-4" />
                                    Sources
                                    <ChevronDown
                                        aria-hidden
                                        className="size-4 group-open:rotate-180"
                                    />
                                </summary>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    AI summary of community posts ·{' '}
                                    {topic.summary_generated_at
                                        ?.slice(0, 16)
                                        .replace('T', ' ')}{' '}
                                    UTC
                                </p>
                                <div
                                    className="flex flex-wrap gap-x-3 gap-y-2 py-2"
                                    aria-label="Summary sources"
                                >
                                    {topic.sources?.map((source, index) => (
                                        <Link
                                            key={source.url}
                                            href={source.url}
                                            className="text-xs text-primary hover:underline"
                                        >
                                            Source {index + 1} · @
                                            {source.author}
                                        </Link>
                                    ))}
                                </div>
                            </details>
                        )}
                    </section>
                )}
            </header>
            {topic.kind === 'subject' && related.length > 0 && (
                <section aria-label="Trending stories">
                    <h2 className="border-b px-4 py-3 font-semibold sm:px-6">
                        Trending stories
                    </h2>
                    {related.map((item) => (
                        <ExploreTopicCard
                            key={item.id}
                            topic={item}
                            reasons={reasons}
                        />
                    ))}
                </section>
            )}
            <PageTabs
                label={
                    topic.kind === 'story' ? 'Story content' : 'Topic commits'
                }
                prefetch={false}
                reset={['commits']}
                tabs={[
                    { value: 'top', title: 'Top' },
                    { value: 'latest', title: 'Latest' },
                    ...(topic.kind === 'story'
                        ? [{ value: 'related', title: 'Related Stories' }]
                        : []),
                ].map(({ value, title }) => ({
                    title,
                    href: { url: `${topic.url}?order=${value}`, method: 'get' },
                    isActive: order === value,
                }))}
            />
            {order === 'related' ? (
                <section aria-label="Related stories">
                    <h2 className="sr-only">Related stories</h2>
                    {related.length > 0 ? (
                        related.map((item) => (
                            <ExploreTopicCard
                                key={item.id}
                                topic={item}
                                reasons={reasons}
                            />
                        ))
                    ) : (
                        <p className="px-4 py-8 text-sm text-muted-foreground sm:px-6">
                            No related stories yet.
                        </p>
                    )}
                </section>
            ) : (
                commits !== null && (
                    <CommitFeed
                        commits={commits}
                        isRanked={order === 'top'}
                        empty="No visible commits in this discussion."
                        end="You're up to date."
                    />
                )
            )}
        </>
    );
}
Show.layout = AppLayout;
