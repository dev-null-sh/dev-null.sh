import { Link, usePage, usePoll } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/HeadlineController';
import { backgroundPollOptions } from '@/lib/background-poll';

/**
 * Hot topics cover the last hours, so they age out without anything being posted: they are read again every few
 * minutes while shown, besides whenever headlines change. Their ranking is deterministic, so reading them again
 * never reshuffles them for nothing.
 */
const ageOut = 5 * 60_000;

export function HotTopics() {
    const { hotTopics = [] } = usePage().props;

    usePoll(ageOut, backgroundPollOptions({ only: ['hotTopics'] }), {
        background: 'pause',
    });

    if (hotTopics.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="aside-hot-topics">
            <h2
                id="aside-hot-topics"
                className="mb-2 flex items-center gap-2 text-sm font-semibold"
            >
                <Flame
                    aria-hidden
                    className="size-4 text-(--lane-0) [&_path]:origin-bottom [&_path]:[transform-box:fill-box] motion-safe:[&_path]:animate-flame-flicker"
                />
                Hot topics
            </h2>
            <ul>
                {hotTopics.map((topic) => (
                    <li key={topic.id}>
                        <Link
                            href={show(topic.id)}
                            className="group -mx-2 flex min-h-8 flex-col justify-center rounded-lg px-2 py-1 text-sm transition-[background-color] hover:bg-accent focus-visible:outline-ring"
                            title={topic.name}
                        >
                            <span className="w-full truncate font-bold group-hover:underline">
                                {topic.name}
                            </span>
                            {topic.summary !== null && (
                                <span className="w-full truncate text-xs text-muted-foreground">
                                    {topic.summary}
                                </span>
                            )}
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
