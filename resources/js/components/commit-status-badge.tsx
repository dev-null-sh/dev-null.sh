import { Check, Clock3 } from 'lucide-react';
import { useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { useCommitStatus } from '@/hooks/use-commit-status';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

const explanations = {
    checking:
        'Running a quick automated check. This usually takes a few seconds, and only you can see this commit until then.',
    review: 'A moderator reviews this commit before anyone else can see it. Commits from established accounts with a following and no recent reports go live right away.',
    rejected: 'This commit was rejected. Only you can see it.',
    approved: 'Your commit is now live for everyone.',
} as const;

/**
 * Tells the author of a commit that is not live yet why, and for roughly how long: a pending commit is either still
 * being checked, which takes seconds, or waiting for a moderator, which can take much longer. Inside a link, where a
 * button cannot go, the explanation is only a title.
 */
export function CommitStatusBadge({
    commit,
    size = 'default',
    isStatic = false,
}: {
    commit: Pick<Commit, 'id' | 'status' | 'is_checking'>;
    size?: 'default' | 'small';
    isStatic?: boolean;
}) {
    const { status, isChecking, isJustApproved } = useCommitStatus(commit);
    const [isOpen, setIsOpen] = useState(false);

    if (status === 'approved' && !isJustApproved) {
        return null;
    }

    const state =
        status === 'pending' ? (isChecking ? 'checking' : 'review') : status;

    const className = cn(
        'inline-flex shrink-0 items-center gap-1 self-center rounded-full font-mono transition-colors',
        size === 'default' ? 'px-2 py-0.5 text-xs' : 'px-1.5 text-[11px]',
        {
            'bg-amber-500/10 text-amber-700 dark:text-amber-400':
                state === 'checking' || state === 'review',
            'bg-destructive/10 text-destructive': state === 'rejected',
            'animate-in bg-emerald-500/10 text-emerald-700 fade-in dark:text-emerald-400':
                state === 'approved',
        },
    );

    const label = (
        <>
            {state === 'checking' && <Spinner aria-hidden className="size-3" />}
            {state === 'review' && <Clock3 aria-hidden className="size-3" />}
            {state === 'approved' && <Check aria-hidden className="size-3" />}
            {state === 'checking'
                ? 'checking…'
                : state === 'review'
                  ? 'in review'
                  : state}
        </>
    );

    if (isStatic) {
        return (
            <span
                className={className}
                title={explanations[state]}
                data-test="commit-status"
            >
                {label}
            </span>
        );
    }

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className={cn(className, 'cursor-help')}
                    aria-live="polite"
                    onPointerEnter={(event) =>
                        event.pointerType === 'mouse' && setIsOpen(true)
                    }
                    onPointerLeave={(event) =>
                        event.pointerType === 'mouse' && setIsOpen(false)
                    }
                    data-test="commit-status"
                >
                    {label}
                </button>
            </PopoverTrigger>
            <PopoverContent
                side="bottom"
                className="w-64 p-3 text-sm"
                onOpenAutoFocus={(event) => event.preventDefault()}
            >
                {explanations[state]}
            </PopoverContent>
        </Popover>
    );
}
