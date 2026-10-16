import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { Check, History, ListChecks, X } from 'lucide-react';
import { useEffect, useEffectEvent, useId, useRef, useState } from 'react';
import CommitApprovalController from '@/actions/App/Http/Controllers/CommitApprovalController';
import CommitClaimController from '@/actions/App/Http/Controllers/CommitClaimController';
import CommitDecisionController from '@/actions/App/Http/Controllers/CommitDecisionController';
import CommitRejectionController from '@/actions/App/Http/Controllers/CommitRejectionController';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import InputError from '@/components/input-error';
import { RejectionReasonsDialog } from '@/components/rejection-reasons-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { UserAvatar } from '@/components/user-avatar';
import { focusCommit } from '@/hooks/use-keyboard-shortcuts';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type {
    Commit,
    CommitClaim,
    CursorPaginated,
    RejectionReason,
} from '@/types';

type ModerationRoute =
    | typeof CommitApprovalController.store
    | typeof CommitRejectionController.store;

function Keycap({ label, className }: { label: string; className?: string }) {
    return (
        <kbd
            aria-hidden
            className={cn(
                '-mr-1 hidden min-w-5 rounded border border-b-2 px-1 text-center font-mono text-xs leading-4 sm:inline-block',
                className,
            )}
        >
            {label}
        </kbd>
    );
}

const claimRefreshMs = 60_000;

function isActive(claim: CommitClaim | undefined, now: Date): boolean {
    return claim !== undefined && new Date(claim.expires_at) > now;
}

function ClaimIndicator({
    claim,
    isMine,
    isDisabled,
    onTakeOver,
}: {
    claim: CommitClaim;
    isMine: boolean;
    isDisabled: boolean;
    onTakeOver: () => void;
}) {
    const now = useNow();
    const minutesLeft = Math.max(
        1,
        Math.round(
            (new Date(claim.expires_at).getTime() - now.getTime()) / 60_000,
        ),
    );

    return (
        <div
            className={cn(
                'mt-3 flex min-h-8 flex-wrap items-center gap-x-2 gap-y-1 rounded-md px-2.5 py-1 text-sm',
                isMine
                    ? 'bg-muted/60 text-muted-foreground'
                    : 'bg-amber-500/10 text-amber-900 dark:text-amber-200',
            )}
            data-test="commit-claim"
        >
            <UserAvatar user={claim.user} className="size-5" />
            <span>
                {isMine ? (
                    'You are reviewing this'
                ) : (
                    <>
                        <span className="font-medium">
                            @{claim.user.username}
                        </span>{' '}
                        is reviewing this
                    </>
                )}
                <span className="text-muted-foreground">
                    {' '}
                    · {minutesLeft}m left
                </span>
            </span>
            {!isMine && (
                <Button
                    variant="outline"
                    size="sm"
                    className="ml-auto h-7 bg-transparent"
                    disabled={isDisabled}
                    onClick={onTakeOver}
                    data-test="take-over-commit-button"
                >
                    Take over
                </Button>
            )}
        </div>
    );
}

function ModerationActions({
    isSelected,
    isDisabled,
    reasons,
    claim,
    isClaimMine,
    error,
    onSelectedChange,
    onTakeOver,
    onErrorClear,
    onModerate,
}: {
    isSelected: boolean;
    isDisabled: boolean;
    reasons: RejectionReason[];
    claim?: CommitClaim;
    isClaimMine: boolean;
    error: string;
    onSelectedChange: (isSelected: boolean) => void;
    onTakeOver: () => void;
    onErrorClear: () => void;
    onModerate: (route: ModerationRoute, reason?: string) => void;
}) {
    const [showReason, setShowReason] = useState(false);
    const [reason, setReason] = useState('');
    const reasonId = useId();
    const reasonInput = useRef<HTMLTextAreaElement>(null);
    const isClaimedByOther = claim !== undefined && !isClaimMine;
    const isLocked = isDisabled || isClaimedByOther;

    const reject = () => onModerate(CommitRejectionController.store, reason);

    const pickReason = (template: RejectionReason) => {
        setShowReason(true);
        setReason(template.body);
        onErrorClear();

        requestAnimationFrame(() => {
            const input = reasonInput.current;

            input?.focus();
            input?.setSelectionRange(
                template.body.length,
                template.body.length,
            );
        });
    };

    return (
        <div>
            {claim && (
                <ClaimIndicator
                    claim={claim}
                    isMine={isClaimMine}
                    isDisabled={isDisabled}
                    onTakeOver={onTakeOver}
                />
            )}
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <Checkbox
                    checked={isSelected}
                    disabled={isLocked}
                    onCheckedChange={(checked) =>
                        onSelectedChange(checked === true)
                    }
                    aria-label="Select commit"
                    className="mr-1"
                    data-test="select-commit-checkbox"
                />
                <Button
                    size="sm"
                    disabled={isLocked}
                    onClick={() => onModerate(CommitApprovalController.store)}
                    aria-keyshortcuts="a"
                    data-test="approve-commit-button"
                >
                    <Check />
                    Approve
                    <Keycap
                        label="a"
                        className="border-primary-foreground/30 bg-primary-foreground/15"
                    />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={isLocked}
                    onClick={reject}
                    aria-keyshortcuts="x"
                    data-test="reject-commit-button"
                >
                    <X />
                    Reject
                    <Keycap label="x" className="bg-muted" />
                </Button>
                {!showReason && (
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={isLocked}
                        onClick={() => setShowReason(true)}
                    >
                        Add reason
                    </Button>
                )}
            </div>
            {reasons.length > 0 && (
                <div
                    role="group"
                    aria-label="Rejection reasons"
                    className={cn(
                        'mt-3 flex-wrap items-center gap-1.5',
                        showReason ? 'flex' : 'hidden group-focus-within:flex',
                    )}
                    data-test="rejection-reason-picker"
                >
                    {reasons.map((template, index) => (
                        <Button
                            key={template.id}
                            variant="outline"
                            size="sm"
                            className={cn(
                                'h-7 gap-1.5 px-2 font-normal',
                                reason === template.body &&
                                    'border-foreground/40 bg-accent',
                            )}
                            disabled={isLocked}
                            aria-pressed={reason === template.body}
                            aria-keyshortcuts={
                                index < 9 ? String(index + 1) : undefined
                            }
                            title={template.body}
                            onClick={() => pickReason(template)}
                        >
                            {index < 9 && (
                                <Keycap
                                    label={String(index + 1)}
                                    className="mr-0 -ml-0.5 bg-muted"
                                />
                            )}
                            {template.title}
                        </Button>
                    ))}
                </div>
            )}
            {showReason && (
                <div className="mt-3 space-y-2">
                    <Label htmlFor={reasonId}>Reason (optional)</Label>
                    <textarea
                        ref={reasonInput}
                        id={reasonId}
                        value={reason}
                        onChange={(event) => {
                            setReason(event.target.value);
                            onErrorClear();
                        }}
                        onKeyDown={(event) => {
                            if (
                                (event.metaKey || event.ctrlKey) &&
                                event.key === 'Enter'
                            ) {
                                event.preventDefault();
                                reject();
                            }
                        }}
                        maxLength={280}
                        disabled={isLocked}
                        aria-invalid={Boolean(error)}
                        aria-describedby={`${reasonId}-help${error ? ` ${reasonId}-error` : ''}`}
                        autoFocus
                        className="block min-h-20 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-[15px] outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50"
                    />
                    <div
                        className="flex items-center justify-between gap-3 text-sm text-muted-foreground"
                        id={`${reasonId}-help`}
                    >
                        <span>
                            Only the author will see this.
                            <span className="hidden sm:inline">
                                {' '}
                                ⌘↵ rejects.
                            </span>
                        </span>
                        <span className="font-mono text-xs">
                            {280 - reason.length}
                        </span>
                    </div>
                </div>
            )}
            <InputError
                id={`${reasonId}-error`}
                message={error}
                className="mt-2"
            />
        </div>
    );
}

export default function Show({
    commits,
    rejectionReasons,
    claims,
}: {
    commits: CursorPaginated<Commit>;
    rejectionReasons: RejectionReason[];
    claims: Record<string, CommitClaim>;
}) {
    const { auth } = usePage().props;
    const now = useNow();
    const [isModerating, setIsModerating] = useState(false);
    const [isManagingReasons, setIsManagingReasons] = useState(false);
    const [failure, setFailure] = useState<{
        commitId: string;
        message: string;
    } | null>(null);

    usePoll(15_000, { only: ['claims'], preserveUrl: true });

    const activeClaim = (commitId: string) =>
        isActive(claims[commitId], now) ? claims[commitId] : undefined;
    const isClaimedByOther = (commitId: string) => {
        const claim = activeClaim(commitId);

        return claim !== undefined && claim.user.id !== auth.user.id;
    };

    const claim = (commitId: string, takeOver = false) =>
        router.visit(CommitClaimController.update(commitId), {
            data: { take_over: takeOver },
            async: true,
            preserveScroll: true,
            preserveState: true,
            preserveUrl: true,
            showProgress: false,
            only: ['claims'],
        });

    const claimFocused = useEffectEvent((commitId: string) => {
        const current = claims[commitId];

        if (current && current.user.id !== auth.user.id) {
            return;
        }

        if (
            current &&
            new Date(current.expires_at).getTime() - Date.now() > claimRefreshMs
        ) {
            return;
        }

        claim(commitId);
    });

    useEffect(() => {
        let timeout: ReturnType<typeof setTimeout> | undefined;

        const onFocusIn = (event: FocusEvent) => {
            clearTimeout(timeout);

            const commitId =
                event.target instanceof HTMLElement
                    ? event.target.closest<HTMLElement>('[data-commit]')
                          ?.dataset.commit
                    : undefined;

            if (commitId) {
                timeout = setTimeout(() => claimFocused(commitId), 300);
            }
        };

        document.addEventListener('focusin', onFocusIn);

        return () => {
            document.removeEventListener('focusin', onFocusIn);
            clearTimeout(timeout);
        };
    }, []);

    const moderate = (
        commit: Commit,
        route: ModerationRoute,
        reason?: string,
    ) => {
        const index = commits.data.findIndex(({ id }) => id === commit.id);
        const neighbour = commits.data[index + 1] ?? commits.data[index - 1];

        setIsModerating(true);
        setFailure(null);

        router.visit(route(commit), {
            data:
                route === CommitRejectionController.store
                    ? { reason: reason ?? '' }
                    : {},
            preserveScroll: true,
            preserveState: 'errors',
            only: ['claims'],
            reset: ['commits'],
            onSuccess: () =>
                requestAnimationFrame(() => {
                    const row = neighbour
                        ? document.querySelector<HTMLElement>(
                              `[data-commit="${neighbour.id}"]`,
                          )
                        : null;

                    if (row) {
                        focusCommit(row);
                    }
                }),
            onFinish: () => setIsModerating(false),
            onError: (errors) =>
                setFailure({
                    commitId: commit.id,
                    message:
                        errors.reason ??
                        errors.commit ??
                        'Could not moderate this commit. Try again.',
                }),
        });
    };

    const [selected, setSelected] = useState<ReadonlySet<string>>(new Set());
    const [approving, setApproving] = useState(false);

    const selectableIds = commits.data
        .map((commit) => commit.id)
        .filter((id) => !isClaimedByOther(id));
    const selectedIds = selectableIds.filter((id) => selected.has(id));
    const isAllSelected =
        selectableIds.length > 0 && selectedIds.length === selectableIds.length;

    const toggle = (id: string, isSelected: boolean) =>
        setSelected((current) => {
            const next = new Set(current);

            if (isSelected) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });

    const toggleAll = (isSelected: boolean) =>
        setSelected(new Set(isSelected ? selectableIds : []));

    const approveSelected = () =>
        router.visit(ModerationController.update(), {
            data: { commits: selectedIds },
            preserveScroll: true,
            reset: ['commits'],
            onStart: () => setApproving(true),
            onSuccess: () => setSelected(new Set()),
            onFinish: () => setApproving(false),
        });

    return (
        <>
            <Head title="Moderation" />

            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center gap-1 border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                    <p className="mr-2 ml-auto hidden text-sm text-muted-foreground sm:block">
                        Oldest first
                    </p>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="ml-auto sm:ml-0"
                        onClick={() => setIsManagingReasons(true)}
                        data-test="manage-rejection-reasons-button"
                    >
                        <ListChecks />
                        Reasons
                    </Button>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={CommitDecisionController.index()}>
                            <History />
                            Decisions
                        </Link>
                    </Button>
                </div>

                {commits.data.length > 0 && (
                    <div className="flex h-12 items-center gap-3 border-b px-4">
                        <Checkbox
                            id="select-all-commits"
                            checked={
                                isAllSelected
                                    ? true
                                    : selectedIds.length > 0
                                      ? 'indeterminate'
                                      : false
                            }
                            disabled={
                                approving ||
                                isModerating ||
                                selectableIds.length === 0
                            }
                            onCheckedChange={(checked) =>
                                toggleAll(checked === true)
                            }
                            data-test="select-all-commits-checkbox"
                        />
                        <label
                            htmlFor="select-all-commits"
                            className="text-sm text-muted-foreground"
                        >
                            {selectedIds.length > 0
                                ? `${selectedIds.length} selected`
                                : 'Select all'}
                        </label>
                        <Button
                            size="sm"
                            className="ml-auto"
                            disabled={
                                approving ||
                                isModerating ||
                                selectedIds.length === 0
                            }
                            onClick={approveSelected}
                            data-test="approve-selected-commits-button"
                        >
                            <Check />
                            {approving
                                ? 'Approving…'
                                : selectedIds.length > 0
                                  ? `Approve ${selectedIds.length} ${selectedIds.length === 1 ? 'commit' : 'commits'}`
                                  : 'Approve selected'}
                        </Button>
                    </div>
                )}
            </div>

            <CommitFeed
                commits={commits}
                firstLane="var(--rail)"
                end="That's every pending commit."
                actions={(commit) => (
                    <ModerationActions
                        isSelected={selected.has(commit.id)}
                        isDisabled={isModerating || approving}
                        reasons={rejectionReasons}
                        claim={activeClaim(commit.id)}
                        isClaimMine={
                            activeClaim(commit.id)?.user.id === auth.user.id
                        }
                        onSelectedChange={(isSelected) =>
                            toggle(commit.id, isSelected)
                        }
                        error={
                            failure?.commitId === commit.id
                                ? failure.message
                                : ''
                        }
                        onTakeOver={() => claim(commit.id, true)}
                        onErrorClear={() => setFailure(null)}
                        onModerate={(route, reason) =>
                            moderate(commit, route, reason)
                        }
                    />
                )}
                empty={
                    <EmptyFeed command="nothing to commit, working tree clean">
                        No commits are waiting for review.
                    </EmptyFeed>
                }
            />

            <RejectionReasonsDialog
                reasons={rejectionReasons}
                open={isManagingReasons}
                onOpenChange={setIsManagingReasons}
            />
        </>
    );
}

Show.layout = AppLayout;
