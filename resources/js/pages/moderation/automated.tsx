import { Head, router } from '@inertiajs/react';
import { Check, Undo2, X } from 'lucide-react';
import { useState } from 'react';
import CommitApprovalController from '@/actions/App/Http/Controllers/CommitApprovalController';
import CommitRejectionController from '@/actions/App/Http/Controllers/CommitRejectionController';
import CommitReopeningController from '@/actions/App/Http/Controllers/CommitReopeningController';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { EvaluationVerdict } from '@/components/evaluation-verdict';
import { ModerationHeader } from '@/components/moderation-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { Commit, CursorPaginated } from '@/types';

type UndoRoute =
    | typeof CommitReopeningController.store
    | typeof CommitApprovalController.store
    | typeof CommitRejectionController.store;

function UndoActions({ commit }: { commit: Commit }) {
    const [isUndoing, setIsUndoing] = useState(false);
    const isOverturned = commit.status !== commit.evaluation?.decision;

    const undo = (route: UndoRoute) =>
        router.post(
            route(commit),
            {},
            {
                preserveScroll: true,
                reset: ['commits'],
                onStart: () => setIsUndoing(true),
                onFinish: () => setIsUndoing(false),
            },
        );

    if (isOverturned) {
        return (
            <p
                className="mt-3 font-mono text-xs text-muted-foreground"
                data-test="overturned"
            >
                Overturned: now {commit.status}.
            </p>
        );
    }

    return (
        <div className="mt-3 flex flex-wrap items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                disabled={isUndoing}
                onClick={() => undo(CommitReopeningController.store)}
                data-test="reopen-commit-button"
            >
                <Undo2 />
                Back to queue
            </Button>
            {commit.status === 'rejected' ? (
                <Button
                    variant="ghost"
                    size="sm"
                    disabled={isUndoing}
                    onClick={() => undo(CommitApprovalController.store)}
                    data-test="approve-commit-button"
                >
                    <Check />
                    Approve instead
                </Button>
            ) : (
                <Button
                    variant="ghost"
                    size="sm"
                    disabled={isUndoing}
                    onClick={() => undo(CommitRejectionController.store)}
                    data-test="reject-commit-button"
                >
                    <X />
                    Reject instead
                </Button>
            )}
        </div>
    );
}

export default function Automated({
    commits,
}: {
    commits: CursorPaginated<Commit>;
}) {
    return (
        <>
            <Head title="Auto-moderated" />

            <ModerationHeader active="automated" aside="Newest first" />

            <CommitFeed
                commits={commits}
                firstLane="var(--rail)"
                end="That's every automatic decision."
                actions={(commit) => (
                    <>
                        <EvaluationVerdict commit={commit} />
                        <UndoActions commit={commit} />
                    </>
                )}
                empty={
                    <EmptyFeed command="git log --author=jev">
                        Nothing was approved or rejected automatically yet.
                        Rules in Live mode decide here; Test mode only records.
                    </EmptyFeed>
                }
            />
        </>
    );
}

Automated.layout = AppLayout;
