import type { ReactNode } from 'react';
import { mentionPattern } from '@/lib/mentions';

// Like a shortened URL, the "@" stays in the text, so copying the bio keeps the mention.
function GitHubMention({ handle }: { handle: string }) {
    const href = `https://github.com/${handle}`;

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            title={href}
            aria-label={`@${handle} on GitHub`}
            className="inline-flex h-[1.55em] items-center gap-[0.3em] rounded-md border bg-muted pr-[0.55em] pl-[calc(0.2em-1px)] align-[-0.3em] text-[0.93em] font-medium whitespace-nowrap text-foreground transition-colors hover:border-primary/45 hover:bg-primary-soft hover:text-primary-hover"
            data-test="bio-mention"
        >
            {/* A handle GitHub doesn't know has no avatar, so the broken image leaves just this placeholder. */}
            <span className="size-[1.15em] shrink-0 overflow-hidden rounded-[calc(var(--radius-md)-0.2em)] bg-border">
                <img
                    src={`${href}.png?size=40`}
                    alt=""
                    width={20}
                    height={20}
                    loading="lazy"
                    className="size-full"
                />
            </span>
            <span>
                <span className="text-[0px]">@</span>
                {handle}
            </span>
        </a>
    );
}

/**
 * A bio or company synced from GitHub, with each tagged user or organization as a badge that opens it on GitHub.
 */
export function BioText({ bio }: { bio: string }) {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const match of bio.matchAll(mentionPattern)) {
        parts.push(
            bio.slice(position, match.index),
            <GitHubMention key={match.index} handle={match[1]} />,
        );
        position = match.index + match[0].length;
    }

    parts.push(bio.slice(position));

    return <>{parts}</>;
}
