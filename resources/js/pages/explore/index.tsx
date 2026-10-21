import { Head, Link } from '@inertiajs/react';
import { Search, Settings2 } from 'lucide-react';
import ExploreController from '@/actions/App/Http/Controllers/ExploreController';
import ExplorePreferenceController from '@/actions/App/Http/Controllers/ExplorePreferenceController';
import SearchController from '@/actions/App/Http/Controllers/SearchController';
import { CommitCard } from '@/components/commit-card';
import {
    ExploreFeedback,
    ExploreTopicCard,
} from '@/components/explore-topic-card';
import { PageTabs } from '@/components/page-tabs';
import { WhoToFollowInLog } from '@/components/who-to-follow';
import AppLayout from '@/layouts/app-layout';
import type { Commit } from '@/types';
import type { ExploreReasons, ExploreTopic } from '@/types/explore';

type Props = {
    tab: string;
    categories: Record<string, string>;
    topics: {
        data: ExploreTopic[];
        prev_page_url: string | null;
        next_page_url: string | null;
        from: number | null;
    };
    today_stories: ExploreTopic[];
    trending_subjects: { category: string; topic: ExploreTopic }[];
    recommended_commits: Commit[];
    reasons: ExploreReasons;
};

function TopicSection({
    title,
    topics,
    reasons,
}: {
    title: string;
    topics: ExploreTopic[];
    reasons: ExploreReasons;
}) {
    return (
        <section aria-label={title}>
            <h2 className="border-b px-4 py-3 text-lg font-semibold sm:px-6">
                {title}
            </h2>
            {topics.length === 0 ? (
                <p className="px-4 py-6 text-sm text-muted-foreground sm:px-6">
                    No matching activity yet. Check back as developers post.
                </p>
            ) : (
                topics.map((topic) => (
                    <ExploreTopicCard
                        key={topic.id}
                        topic={topic}
                        reasons={reasons}
                    />
                ))
            )}
        </section>
    );
}

export default function Index(props: Props) {
    const tabs = {
        explore: 'Explore',
        trending: 'Trending',
        ...props.categories,
    };
    return (
        <>
            <Head title="Explore" />
            <header className="sticky top-0 z-10 border-b bg-card">
                <div className="flex items-center gap-3 px-4 py-3 sm:px-6">
                    <h1 className="text-lg font-semibold">Explore</h1>
                    <Link
                        href={SearchController.index()}
                        className="ml-auto flex min-h-10 items-center gap-2 rounded-full border px-4 text-sm text-muted-foreground hover:bg-accent"
                    >
                        <Search aria-hidden className="size-4" /> Search
                    </Link>
                    <Link
                        href={ExplorePreferenceController.show()}
                        aria-label="Explore settings"
                        className="flex size-10 items-center justify-center rounded-full hover:bg-accent"
                    >
                        <Settings2 aria-hidden className="size-5" />
                    </Link>
                </div>
                <div className="overflow-x-auto [&_a]:shrink-0 [&_a]:px-4 [&_a]:whitespace-nowrap [&_nav]:w-max [&_nav]:min-w-full">
                    <PageTabs
                        label="Explore categories"
                        prefetch={false}
                        tabs={Object.entries(tabs).map(([value, title]) => ({
                            title,
                            href: ExploreController.index({
                                query: { tab: value },
                            }),
                            isActive: props.tab === value,
                        }))}
                    />
                </div>
            </header>
            {props.tab === 'explore' ? (
                <>
                    <TopicSection
                        title="Today's stories"
                        topics={props.today_stories}
                        reasons={props.reasons}
                    />
                    <section aria-label="Trending">
                        <h2 className="border-b px-4 py-3 text-lg font-semibold sm:px-6">
                            Trending
                        </h2>
                        {props.trending_subjects.length === 0 ? (
                            <p className="px-4 py-6 text-sm text-muted-foreground sm:px-6">
                                No matching activity yet. Check back as
                                developers post.
                            </p>
                        ) : (
                            props.trending_subjects.map(
                                ({ category, topic }) => (
                                    <article
                                        key={category}
                                        className="border-b px-4 py-4 sm:px-6"
                                    >
                                        <div className="flex items-start gap-3">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-xs text-muted-foreground">
                                                    {props.categories[category]}{' '}
                                                    · Trending
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground tabular-nums">
                                                    {topic.commits_count}{' '}
                                                    {topic.commits_count === 1
                                                        ? 'commit'
                                                        : 'commits'}{' '}
                                                    · {topic.contributors_count}{' '}
                                                    {topic.contributors_count ===
                                                    1
                                                        ? 'developer'
                                                        : 'developers'}
                                                </p>
                                                <Link
                                                    href={topic.url}
                                                    className="mt-1 block text-base font-semibold break-words hover:underline"
                                                >
                                                    #{topic.title}
                                                </Link>
                                            </div>
                                            <ExploreFeedback
                                                topic={topic}
                                                reasons={props.reasons}
                                            />
                                        </div>
                                    </article>
                                ),
                            )
                        )}
                    </section>
                    <WhoToFollowInLog />
                    <section aria-label="Recommended commits">
                        <h2 className="border-b px-4 py-3 text-lg font-semibold sm:px-6">
                            Recommended commits
                        </h2>
                        {props.recommended_commits.length === 0 ? (
                            <p className="px-4 py-8 text-sm text-muted-foreground sm:px-6">
                                Relevant commits will appear here as discussions
                                develop.
                            </p>
                        ) : (
                            props.recommended_commits.map((commit) => (
                                <CommitCard key={commit.id} commit={commit} />
                            ))
                        )}
                    </section>
                </>
            ) : (
                <>
                    <h2 className="sr-only">
                        {tabs[props.tab as keyof typeof tabs] ?? props.tab}
                    </h2>
                    {props.topics.data.length === 0 ? (
                        <p className="px-4 py-12 text-center text-muted-foreground">
                            No matching subjects or stories yet.
                        </p>
                    ) : (
                        props.topics.data.map((topic, index) => (
                            <ExploreTopicCard
                                key={topic.id}
                                topic={topic}
                                reasons={props.reasons}
                                rank={
                                    props.tab === 'trending'
                                        ? (props.topics.from ?? 1) + index
                                        : undefined
                                }
                            />
                        ))
                    )}
                    <nav
                        aria-label="Explore pages"
                        className="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6"
                    >
                        {props.topics.prev_page_url !== null && (
                            <Link
                                href={props.topics.prev_page_url}
                                className="text-primary hover:underline"
                            >
                                Previous
                            </Link>
                        )}
                        {props.topics.next_page_url !== null && (
                            <Link
                                href={props.topics.next_page_url}
                                className="ml-auto text-primary hover:underline"
                            >
                                Next
                            </Link>
                        )}
                    </nav>
                </>
            )}
        </>
    );
}
Index.layout = AppLayout;
