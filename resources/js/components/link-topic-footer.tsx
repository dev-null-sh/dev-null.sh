import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { AuthorAvatar } from '@/components/author-avatar';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type { LinkTopic } from '@/types';

/**
 * The strip at the bottom of a link card, once others talk about the link: who, and how many commits, leading to the
 * link's page. In a quoted commit, which is a link itself, it only says so.
 */
export function LinkTopicFooter({
    topic,
    isStatic = false,
}: {
    topic: LinkTopic;
    isStatic?: boolean;
}) {
    const label = `${formatCount(topic.others)} other commit${topic.others === 1 ? '' : 's'} about this link`;
    const content = (
        <>
            {topic.authors.length > 0 && (
                <span aria-hidden className="flex shrink-0">
                    {topic.authors.map((author) => (
                        <AuthorAvatar
                            key={author.id}
                            author={author}
                            className="-ml-1.5 size-5 ring-2 ring-card first:ml-0"
                        />
                    ))}
                </span>
            )}
            <span className="min-w-0 truncate tabular-nums">{label}</span>
        </>
    );
    const className =
        'flex min-h-8 items-center gap-2 border-t bg-muted/40 px-3 py-1.5 text-[13px] text-muted-foreground';

    if (isStatic) {
        return (
            <span className={className} data-test="link-topic-footer">
                {content}
            </span>
        );
    }

    return (
        <Link
            prefetch
            href={topic.url}
            onClick={(event) => event.stopPropagation()}
            className={cn(
                className,
                'transition-colors hover:bg-accent hover:text-foreground focus-visible:bg-accent',
            )}
            data-test="link-topic-footer"
        >
            {content}
            <ChevronRight aria-hidden className="ml-auto size-4 shrink-0" />
        </Link>
    );
}
