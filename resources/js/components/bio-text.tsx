import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { mentionPattern } from '@/lib/mentions';
import { show } from '@/routes/user';

const mentionClassName =
    'inline-flex h-[1.55em] items-center gap-[0.3em] rounded-md border bg-muted pr-[0.55em] pl-[calc(0.2em-1px)] align-[-0.3em] text-[0.93em] font-medium whitespace-nowrap text-foreground transition-colors hover:border-primary/45 hover:bg-primary-soft hover:text-primary-hover';

// Like a shortened URL, the "@" stays in the text, so copying the bio keeps the mention.
function MentionContent({ handle }: { handle: string }) {
    return (
        <>
            {/* A handle GitHub doesn't know has no avatar, so the broken image leaves just this placeholder. */}
            <span className="size-[1.15em] shrink-0 overflow-hidden rounded-[calc(var(--radius-md)-0.2em)] bg-border">
                <img
                    src={`https://github.com/${handle}.png?size=40`}
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
        </>
    );
}

function Mention({
    handle,
    profile,
}: {
    handle: string;
    profile: string | null;
}) {
    if (profile !== null) {
        return (
            <Link
                href={show(profile)}
                aria-label={`@${handle}`}
                className={mentionClassName}
                data-test="bio-mention"
            >
                <MentionContent handle={handle} />
            </Link>
        );
    }

    const href = `https://github.com/${handle}`;

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            title={href}
            aria-label={`@${handle} on GitHub`}
            className={mentionClassName}
            data-test="bio-mention"
        >
            <MentionContent handle={handle} />
        </a>
    );
}

/**
 * A bio or company synced from GitHub, with each tagged user or organization as a badge. The badge opens the profile
 * on dev/null when there is one, by lowercase handle in `profiles`, and on GitHub otherwise.
 */
export function BioText({
    bio,
    profiles = null,
}: {
    bio: string;
    profiles?: Record<string, string> | null;
}) {
    const parts: ReactNode[] = [];
    let position = 0;

    for (const match of bio.matchAll(mentionPattern)) {
        // PHP sends no profiles as an empty array, whose "length" isn't a handle.
        const profile = profiles?.[match[1].toLowerCase()];

        parts.push(
            bio.slice(position, match.index),
            <Mention
                key={match.index}
                handle={match[1]}
                profile={typeof profile === 'string' ? profile : null}
            />,
        );
        position = match.index + match[0].length;
    }

    parts.push(bio.slice(position));

    return <>{parts}</>;
}
