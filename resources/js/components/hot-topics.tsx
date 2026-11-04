import { Link, usePage } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import { useId } from 'react';
import { show } from '@/actions/App/Http/Controllers/HeadlineController';

export function HotTopics() {
    const { hotTopics = [] } = usePage().props;
    const headingId = useId();

    if (hotTopics.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby={headingId}>
            <h2
                id={headingId}
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
                            prefetch
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
