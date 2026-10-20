import type { ReactNode } from 'react';
import { mentionPattern } from '@/lib/mentions';

// Like a shortened URL, the "@" stays in the text, so cop

// ying the bio keeps the mention.
function GitHubMention({ username }: { username: string }) {
    const href = `https://github.com/${username}`;

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            title={href}
            aria-label={`@${username} on GitHub`}
            className="inline-flex h-[1.55em] items-center gap-[0.3em] rounded-full border bg-muted pr-[0.55em] pl-[0.15em] align-[-0.3em] text-[0.93em] font-medium whitespace-nowrap text-foreground transition-colors hover:border-primary/45 hover:bg-primary-soft hover:text-primary-hover"
            data-test="bio-mention"
        >
            {/* A handle GitHub doesn't know has no avatar, so the broken image leaves just this circle. */}
            <span className="size-[1.15em] shrink-0 overflow-hidden rounded-full bg-border">
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
                {username}
            </span>
        </a>
    );
}

/**
 * A bio or company synced from GitHub, with each tagged user or organization as a pill that opens it on GitHub.
 */
export function BioText({ bio }: { bio: string }) {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const match of bio.matchAll(mentionPattern)) {
        parts.push(
            bio.slice(position, match.index),
            <GitHubMention key={match.index} username={match[1]} />,
        );
        position = match.index + match[0].length;
    }

    parts.push(bio.slice(position));

    return <>{parts}</>;
}
