import { Head, router } from '@inertiajs/react';
import { Bot, Check, X } from 'lucide-react';
import { useId, useState } from 'react';
import { toast } from 'sonner';
import CommitApprovalController from '@/actions/App/Http/Controllers/CommitApprovalController';
import CommitRejectionController from '@/actions/App/Http/Controllers/CommitRejectionController';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { ModerationTabs } from '@/components/moderation-tabs';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { focusCommit } from '@/hooks/use-keyboard-shortcuts';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Commit, CursorPaginated } from '@/types';

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

function AiDecision({ commit }: { commit: Commit }) {
    const categories = (commit.ai_categories ?? []).join(', ');
    const label =
        commit.ai_decision === 'approved'
            ? 'AI suggests approving'
            : commit.ai_decision === 'rejected'
              ? `AI suggests rejecting${categories ? `: ${categories}` : ''}`
              : 'No AI decision yet';

    return (
        <p
            className={cn(
                'mt-3 flex h-5 min-w-0 items-center gap-1.5 text-[13px]',
                commit.ai_decision === 'rejected'
                    ? 'text-destructive'
                    : 'text-muted-foreground',
            )}
            title={label}
            data-test="ai-decision"
        >
            <Bot aria-hidden className="size-4 shrink-0" />
            <span className="truncate">{label}</span>
        </p>
    );
}

function ModerationActions({
    isSelected,
    onSelectedChange,
    onModerate,
}: {
    isSelected: boolean;
    onSelectedChange: (isSelected: boolean) => void;
    onModerate: (route: ModerationRoute, reason: string) => void;
}) {
    const [showReason, setShowReason] = useState(false);
    const [reason, setReason] = useState('');
    const reasonId = useId();

    return (
        <div>
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <Checkbox
                    checked={isSelected}
                    onCheckedChange={(checked) =>
                        onSelectedChange(checked === true)
                    }
                    aria-label="Select commit"
                    className="mr-1"
                    data-test="select-commit-checkbox"
                />
                <Button
                    size="sm"
                    onClick={() =>
                        onModerate(CommitApprovalController.store, '')
                    }
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
                    onClick={() =>
                        onModerate(CommitRejectionController.store, reason)
                    }
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
                        onClick={() => setShowReason(true)}
                    >
                        Add reason
                    </Button>
                )}
            </div>
            {showReason && (
                <div className="mt-3 space-y-2">
                    <Label htmlFor={reasonId}>Reason (optional)</Label>
                    <textarea
                        id={reasonId}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        maxLength={280}
                        aria-describedby={`${reasonId}-help`}
                        autoFocus
                        className="block min-h-20 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50 md:text-[15px]"
                    />
                    <div
                        className="flex items-center justify-between gap-3 text-sm text-muted-foreground"
                        id={`${reasonId}-help`}
                    >
                        <span>Only the author will see this.</span>
                        <span className="font-mono text-xs">
                            {280 - reason.length}
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}

function withoutCommits(
    props: { commits: CursorPaginated<Commit> },
    ids: ReadonlySet<string>,
): { commits: CursorPaginated<Commit> } {
    return {
        commits: {
            ...props.commits,
            data: props.commits.data.filter((commit) => !ids.has(commit.id)),
        },
    };
}

export default function Show({
    commits,
}: {
    commits: CursorPaginated<Commit>;
}) {
    const commitNotFound = useCommitNotFound();

    const moderate = (
        commit: Commit,
        route: ModerationRoute,
        reason: string,
    ) => {
        const index = commits.data.findIndex(({ id }) => id === commit.id);
        const neighbour = commits.data[index + 1] ?? commits.data[index - 1];

        router
            .optimistic((props: { commits: CursorPaginated<Commit> }) =>
                withoutCommits(props, new Set([commit.id])),
            )
            .visit(route(commit), {
                data:
                    route === CommitRejectionController.store ? { reason } : {},
                preserveScroll: true,
                only: ['commits', 'hasUnmoderatedCommits'],
                reset: ['commits'],
                onHttpException: (response) =>
                    !commitNotFound(commit.id, response),
                onError: (errors) =>
                    toast.error(
                        errors.reason ??
                            'Could not moderate this commit. Try again.',
                    ),
            });

        requestAnimationFrame(() => {
            const row = neighbour
                ? document.querySelector<HTMLElement>(
                      `[data-commit="${neighbour.id}"]`,
                  )
                : null;

            if (row) {
                focusCommit(row);
            }
        });
    };

    const [selected, setSelected] = useState<ReadonlySet<string>>(new Set());

    const selectedIds = commits.data
        .map((commit) => commit.id)
        .filter((id) => selected.has(id));
    const isAllSelected =
        commits.data.length > 0 && selectedIds.length === commits.data.length;

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
        setSelected(
            new Set(isSelected ? commits.data.map((commit) => commit.id) : []),
        );

    const approveSelected = () =>
        router
            .optimistic((props: { commits: CursorPaginated<Commit> }) =>
                withoutCommits(props, new Set(selectedIds)),
            )
            .visit(ModerationController.update(), {
                data: { commits: selectedIds },
                preserveScroll: true,
                only: ['commits', 'hasUnmoderatedCommits'],
                reset: ['commits'],
                onSuccess: () => setSelected(new Set()),
            });

    return (
        <>
            <Head title="Moderation" />

            <div className="sticky top-0 z-10 bg-card">
                <div className="flex h-14 items-center border-b px-4">
                    <h1 className="font-semibold">Moderation</h1>
                    <p className="ml-auto text-sm text-muted-foreground">
                        Oldest first
                    </p>
                </div>

                <ModerationTabs active="pending" />

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
                            disabled={selectedIds.length === 0}
                            onClick={approveSelected}
                            data-test="approve-selected-commits-button"
                        >
                            <Check />
                            {selectedIds.length > 0
                                ? `Approve ${selectedIds.length} ${selectedIds.length === 1 ? 'commit' : 'commits'}`
                                : 'Approve selected'}
                        </Button>
                    </div>
                )}
            </div>

            <CommitFeed
                commits={commits}
                end="That's every pending commit."
                isFolded={false}
                actions={(commit) => (
                    <>
                        <AiDecision commit={commit} />
                        <ModerationActions
                            isSelected={selected.has(commit.id)}
                            onSelectedChange={(isSelected) =>
                                toggle(commit.id, isSelected)
                            }
                            onModerate={(route, reason) =>
                                moderate(commit, route, reason)
                            }
                        />
                    </>
                )}
                empty={
                    <EmptyFeed command="nothing to commit, working tree clean">
                        No commits are waiting for review.
                    </EmptyFeed>
                }
            />
        </>
    );
}

Show.layout = AppLayout;
