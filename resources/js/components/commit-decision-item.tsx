import { Link } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import {
    cn,
    formatFullDateTime,
    formatRelativeTime,
    isDeleted,
} from '@/lib/utils';
import type { CommitDecision, LoggedCommitDecision } from '@/types';

function DecisionIcon({ decision }: { decision: CommitDecision }) {
    const Icon = decision.status === 'approved' ? Check : X;

    return (
        <Icon
            aria-hidden
            className={cn(
                'mt-0.5 size-5 shrink-0',
                decision.status === 'approved'
                    ? 'text-primary'
                    : 'text-destructive',
            )}
        />
    );
}

function DecisionTime({ decision }: { decision: CommitDecision }) {
    const now = useNow();

    return (
        <time
            dateTime={decision.created_at}
            title={formatFullDateTime(decision.created_at)}
        >
            {formatRelativeTime(decision.created_at, now)}
        </time>
    );
}

function Moderator({ decision }: { decision: CommitDecision }) {
    return decision.user ? (
        <span className="font-medium">
            <UserAvatar
                user={decision.user}
                className="mr-1.5 inline-flex size-5 align-[-5px]"
            />
            @{decision.user.username}
        </span>
    ) : (
        <span className="font-medium">A former admin</span>
    );
}

function Reason({ decision }: { decision: CommitDecision }) {
    return decision.reason ? (
        <p className="mt-1 text-sm wrap-anywhere whitespace-pre-wrap">
            <span className="font-medium">Reason: </span>
            {decision.reason}
        </p>
    ) : null;
}

export function CommitDecisionSummary({
    decision,
}: {
    decision: CommitDecision;
}) {
    return (
        <li className="flex gap-3 py-2" data-test="commit-decision">
            <DecisionIcon decision={decision} />
            <div className="min-w-0 flex-1 text-[15px]">
                <p className="wrap-anywhere">
                    {decision.status === 'approved' ? 'Approved' : 'Rejected'}{' '}
                    by <Moderator decision={decision} />
                    <span className="text-muted-foreground">
                        <span aria-hidden> · </span>
                        <DecisionTime decision={decision} />
                    </span>
                </p>
                <Reason decision={decision} />
            </div>
        </li>
    );
}

export function CommitDecisionItem({
    decision,
}: {
    decision: LoggedCommitDecision;
}) {
    const { commit } = decision;

    return (
        <li
            className="flex gap-3 px-4 py-3 sm:px-6"
            data-test="commit-decision"
        >
            <DecisionIcon decision={decision} />
            <div className="min-w-0 flex-1">
                <p className="text-[15px] wrap-anywhere">
                    <Moderator decision={decision} /> {decision.status}{' '}
                    {isDeleted(commit) ? (
                        'a commit that was since deleted'
                    ) : (
                        <>
                            <span className="font-medium">
                                @{commit.user.username}
                            </span>
                            ’s commit
                        </>
                    )}
                    <span className="text-muted-foreground">
                        <span aria-hidden> · </span>
                        <DecisionTime decision={decision} />
                    </span>
                </p>
                {!isDeleted(commit) &&
                    (commit.status === 'approved' ? (
                        <Link
                            href={CommitController.show({
                                user: commit.user,
                                commit,
                            })}
                            className="mt-1 block truncate rounded-sm text-[15px] text-muted-foreground hover:text-foreground"
                        >
                            {commit.body}
                        </Link>
                    ) : (
                        <p className="mt-1 truncate text-[15px] text-muted-foreground">
                            {commit.body}
                        </p>
                    ))}
                <Reason decision={decision} />
            </div>
        </li>
    );
}

export function CommitDecisionSkeleton() {
    return (
        <div aria-hidden className="flex gap-3 px-4 py-3 sm:px-6">
            <span className="mt-0.5 size-5 shrink-0 rounded-full bg-muted" />
            <div className="flex-1 text-[15px] motion-safe:animate-pulse">
                <p className="flex items-center gap-1.5">
                    <span className="size-5 rounded-full bg-muted" />
                    <span className="h-3 w-3/5 rounded bg-muted" />
                    &#8203;
                </p>
                <p className="mt-1 flex items-center">
                    <span className="h-3 w-4/5 rounded bg-muted" />
                    &#8203;
                </p>
            </div>
        </div>
    );
}
