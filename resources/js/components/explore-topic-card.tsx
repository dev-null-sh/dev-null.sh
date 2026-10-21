import { Link, router, usePage } from '@inertiajs/react';
import { Ellipsis } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import ExploreFeedbackController from '@/actions/App/Http/Controllers/ExploreFeedbackController';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ExploreReasons, ExploreTopic } from '@/types/explore';

export function ExploreFeedback({
    topic,
    reasons,
}: {
    topic: Pick<ExploreTopic, 'id' | 'title'>;
    reasons: ExploreReasons;
}) {
    const user = usePage().props.auth.user;
    const [pending, setPending] = useState(false);
    if (user === null) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    disabled={pending}
                    aria-label={`Feedback on ${topic.title}`}
                    className="flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <Ellipsis aria-hidden className="size-5" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-[70dvh] max-w-[min(24rem,90vw)] overflow-y-auto"
            >
                {Object.entries(reasons).map(([reason, label]) => (
                    <DropdownMenuItem
                        key={reason}
                        onSelect={() => {
                            setPending(true);
                            router.post(
                                ExploreFeedbackController.store(topic.id),
                                { reason },
                                {
                                    preserveScroll: true,
                                    onError: () =>
                                        toast.error(
                                            'Could not submit feedback. Please try again.',
                                        ),
                                    onFinish: () => setPending(false),
                                },
                            );
                        }}
                    >
                        {label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function ExploreTopicCard({
    topic,
    reasons,
    rank,
}: {
    topic: ExploreTopic;
    reasons: ExploreReasons;
    rank?: number;
}) {
    return (
        <article
            className="border-b px-4 py-4 sm:px-6"
            data-test="explore-topic"
        >
            <div className="flex items-start gap-3">
                {rank !== undefined && (
                    <span className="w-6 shrink-0 pt-1 font-mono text-sm text-muted-foreground tabular-nums">
                        {rank}
                    </span>
                )}
                <div className="min-w-0 flex-1">
                    <p className="text-xs text-muted-foreground">
                        {topic.kind === 'subject'
                            ? 'Trending subject'
                            : 'Community story'}
                        {topic.categories.length > 0 &&
                            ` · ${topic.categories.map((category) => category.replaceAll('-', ' ')).join(' · ')}`}
                    </p>
                    <Link
                        href={topic.url}
                        className="mt-1 block text-base font-semibold break-words hover:underline"
                    >
                        {topic.kind === 'subject' ? '#' : ''}
                        {topic.title}
                    </Link>
                    <p className="mt-1 text-sm text-muted-foreground tabular-nums">
                        {topic.commits_count}{' '}
                        {topic.commits_count === 1 ? 'commit' : 'commits'} ·{' '}
                        {topic.contributors_count}{' '}
                        {topic.contributors_count === 1
                            ? 'developer'
                            : 'developers'}
                    </p>
                    {topic.subjects.length > 0 && (
                        <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1">
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
                </div>
                <ExploreFeedback topic={topic} reasons={reasons} />
            </div>
        </article>
    );
}
