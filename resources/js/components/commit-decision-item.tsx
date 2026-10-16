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
        <span className="inline-flex items-center gap-1.5 align-bottom font-medium">
            <UserAvatar user={decision.user} className="size-5" />@
            {decision.user.username}
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
                <p>
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
                <p className="text-[15px]">
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
                            className="mt-1 block truncate text-[15px] text-muted-foreground hover:text-foreground"
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
