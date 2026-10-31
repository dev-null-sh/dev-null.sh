import { Link } from '@inertiajs/react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitLinkPreviewController from '@/actions/App/Http/Controllers/CommitLinkPreviewController';
import { CommitBodyExcerpt } from '@/components/commit-body';
import { CommitImages } from '@/components/commit-images';
import { CommitPoll } from '@/components/commit-poll';
import { CommitStatusBadge } from '@/components/commit-status-badge';
import { LinkPreview } from '@/components/link-preview';
import { UserAvatar } from '@/components/user-avatar';
import { useDateTime } from '@/hooks/use-date-time';
import { useNow } from '@/hooks/use-now';
import { cn, isDeleted } from '@/lib/utils';
import type { DeletedCommit, QuotedCommit as QuotedCommitData } from '@/types';

export function QuotedCommit({
    commit,
    isPreview = false,
    label = 'Quoting',
}: {
    commit: QuotedCommitData | DeletedCommit | null;
    isPreview?: boolean;
    label?: string;
}) {
    const { formatFullDateTime, formatRelativeTime } = useDateTime();
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

    const className = 'mt-2 block rounded-lg border px-3 py-2';

    const content = (
        <>
            <span className="flex min-w-0 items-center gap-1.5 text-sm">
                <span className="sr-only">{label}</span>
                <UserAvatar user={commit.user} className="size-5" />
                <span className="truncate font-semibold">
                    {commit.user.name}
                </span>
                <span className="truncate font-mono text-xs text-muted-foreground">
                    @{commit.user.handle}
                </span>
                <span aria-hidden className="text-muted-foreground">
                    ·
                </span>
                <time
                    dateTime={commit.created_at}
                    title={formatFullDateTime(commit.created_at)}
                    className="shrink-0 font-mono text-xs text-muted-foreground"
                >
                    {formatRelativeTime(commit.created_at, now)}
                </time>
                <CommitStatusBadge commit={commit} size="small" isStatic />
            </span>
            <CommitBodyExcerpt
                segments={commit.body_segments}
                previewSegments={commit.preview_segments}
                className="mt-1 max-h-60"
            />
            {commit.images && <CommitImages images={commit.images} />}
            {commit.poll && (
                <CommitPoll commit={commit} poll={commit.poll} isStatic />
            )}
            {commit.link_preview && (
                <LinkPreview
                    refreshUrl={CommitLinkPreviewController.show.url(commit.id)}
                    preview={commit.link_preview}
                    isStatic
                />
            )}
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
