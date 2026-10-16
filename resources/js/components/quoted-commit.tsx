import { Link } from '@inertiajs/react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { CommitBodyExcerpt } from '@/components/commit-body';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import { cn, formatRelativeTime, isDeleted } from '@/lib/utils';
import type {
    CommitImage,
    DeletedCommit,
    QuotedCommit as QuotedCommitData,
} from '@/types';

export function QuotedCommit({
    commit,
    isPreview = false,
}: {
    commit: QuotedCommitData | DeletedCommit | null;
    isPreview?: boolean;
}) {
    const now = useNow();
    if (commit === null || isDeleted(commit)) {
        return (
            <p
                className="mt-2 flex items-baseline gap-1.5 rounded-lg border border-dashed px-3 py-2 text-sm text-muted-foreground"
                data-test="quoted-commit-tombstone"
            >
                <span className="font-mono text-xs">
                    {commit === null ? '[unavailable]' : '[deleted]'}
                </span>
                {commit === null
                    ? "This commit isn't available."
                    : 'This commit was deleted.'}
            </p>
        );
    }

    const images = commit.images ?? [];
    const className = 'mt-2 block rounded-lg border px-3 py-2';

    const content = (
        <>
            <span className="flex min-w-0 items-center gap-1.5 text-sm">
                <span className="sr-only">Quoting</span>
                <UserAvatar user={commit.user} className="size-5" />
                <span className="truncate font-semibold">
                    {commit.user.name}
                </span>
                <span className="truncate font-mono text-xs text-muted-foreground">
                    @{commit.user.username}
                </span>
                <span aria-hidden className="text-muted-foreground">
                    ·
                </span>
                <time
                    dateTime={commit.created_at}
                    title={new Date(commit.created_at).toLocaleString()}
                    className="shrink-0 font-mono text-xs text-muted-foreground"
                >
                    {formatRelativeTime(commit.created_at, now)}
                </time>
                {commit.status !== 'approved' && (
                    <span
                        className={cn(
                            'shrink-0 rounded-full px-1.5 font-mono text-[11px]',
                            commit.status === 'pending'
                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                : 'bg-destructive/10 text-destructive',
                        )}
                    >
                        {commit.status}
                    </span>
                )}
            </span>
            <div className="mt-1 flex items-start gap-3">
                <CommitBodyExcerpt
                    segments={commit.body_segments}
                    className="max-h-60 min-w-0 flex-1"
                />
                {images.length > 0 && <QuotedMedia images={images} />}
            </div>
        </>
    );

    if (isPreview) {
        return (
            <div className={className} data-test="quoted-commit">
                {content}
            </div>
        );
    }

    return (
        <Link
            href={CommitController.show({ user: commit.user, commit })}
            className={cn(
                className,
                'transition-colors hover:bg-accent focus-visible:bg-accent',
            )}
            data-test="quoted-commit"
        >
            {content}
        </Link>
    );
}

/**
 * A teaser of the quoted commit's images, not the gallery: one thumbnail on the side, dimmed by a gradient and
 * counting the rest, so the full picture stays one click away on the original commit.
 */
function QuotedMedia({ images }: { images: CommitImage[] }) {
    const [first] = images;

    return (
        <span
            className="relative mt-0.5 block size-20 shrink-0 overflow-hidden rounded-md border bg-muted sm:size-24"
            data-test="quoted-commit-media"
        >
            <img
                src={first.url}
                alt=""
                loading="lazy"
                decoding="async"
                width={first.width}
                height={first.height}
                className="size-full object-cover"
            />
            <span
                aria-hidden
                className="absolute inset-0 bg-linear-to-t from-black/60 via-black/15 to-transparent"
            />
            {images.length > 1 && (
                <span className="absolute right-1.5 bottom-1 font-mono text-[11px] font-medium text-white tabular-nums">
                    +{images.length - 1}
                </span>
            )}
            <span className="sr-only">
                {images.length === 1
                    ? 'With 1 image'
                    : `With ${images.length} images`}
            </span>
        </span>
    );
}
