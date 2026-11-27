import { Link } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/UserController';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

/**
 * Tells moderators who posted an organization's commit, which nobody else sees, with a link to their profile to time
 * them out or ban them from there.
 */
export function CommitPoster({
    commit,
    className,
}: {
    commit: Pick<Commit, 'poster'>;
    className?: string;
}) {
    if (commit.poster === undefined) {
        return null;
    }

    return (
        <p
            className={cn(
                'flex min-w-0 items-center gap-1.5 text-[13px] text-muted-foreground',
                className,
            )}
            data-test="commit-poster"
        >
            <UserRound aria-hidden className="size-4 shrink-0" />
            {commit.poster === null ? (
                <span>Posted by an account since deleted</span>
            ) : (
                <span
                    className="truncate"
                    title={`Posted by @${commit.poster.handle}`}
                >
                    Posted by{' '}
                    <Link
                        prefetch
                        href={show(commit.poster)}
                        className="font-mono text-foreground hover:underline"
                    >
                        @{commit.poster.handle}
                    </Link>
                </span>
            )}
        </p>
    );
}
