import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { CommitCard, CommitTombstone } from '@/components/commit-card';
import { CommitDecisionSummary } from '@/components/commit-decision-item';
import { CopyLinkButton } from '@/components/copy-link-button';
import AppLayout from '@/layouts/app-layout';
import { isDeleted, laneOf, shortHash } from '@/lib/utils';
import { home } from '@/routes';
import type {
    Commit,
    CommitDecision,
    CursorPaginated,
    ThreadCommit,
} from '@/types';

const titleLength = 60;

export default function Show({
    commit,
    ancestors,
    series,
    replies,
    decisions,
}: {
    commit: Commit;
    ancestors: ThreadCommit[];
    series: ThreadCommit[];
    replies: CursorPaginated<ThreadCommit> | null;
    decisions: CommitDecision[];
}) {
    const { auth } = usePage().props;
    const excerpt =
        commit.body.length > titleLength
            ? `${commit.body.slice(0, titleLength).trimEnd()}…`
            : commit.body;

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(auth.log === null ? home() : LogController.show(auth.log));
    };

    const lastAncestor = ancestors.at(-1);
    const isSeries = commit.series_id !== null;

    return (
        <>
            <Head title={`${commit.user.name}: “${excerpt}”`} />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <button
                    type="button"
                    onClick={goBack}
                    aria-label="Back"
                    className="text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </button>
                <h1 className="font-semibold">Commit</h1>
                <p className="ml-auto shrink-0 font-mono text-[13px] text-muted-foreground">
                    {shortHash(commit.id)}
                </p>
                <CopyLinkButton commit={commit} className="-mr-1 -ml-1.5" />
            </div>

            {decisions.length > 0 && (
                <section
                    aria-labelledby="moderation-decisions"
                    className="border-b bg-muted/30 px-4 py-2 sm:px-6"
                >
                    <h2
                        id="moderation-decisions"
                        className="pt-1 font-mono text-xs text-muted-foreground"
                    >
                        Moderation (only admins see this)
                    </h2>
                    <ul className="divide-y">
                        {decisions.map((decision) => (
                            <CommitDecisionSummary
                                key={decision.id}
                                decision={decision}
                            />
                        ))}
                    </ul>
                </section>
            )}

            {ancestors.map((ancestor, index) => {
                const previous = ancestors[index - 1];
                const previousLane = previous ? laneOf(previous) : undefined;

                return isDeleted(ancestor) ? (
                    <CommitTombstone
                        key={ancestor.id}
                        commit={ancestor}
                        previousLane={previousLane}
                    />
                ) : (
                    <CommitCard
                        key={ancestor.id}
                        commit={ancestor}
                        previousLane={previousLane}
                        isGroupedWithPrevious={isSameAuthor(previous, ancestor)}
                        canDelete={isSeries}
                        isSeriesExpanded
                    />
                );
            })}

            <CommitCard
                key={commit.id}
                commit={commit}
                previousLane={lastAncestor ? laneOf(lastAncestor) : undefined}
                isGroupedWithPrevious={isSameAuthor(lastAncestor, commit)}
                initialReplies={replies ?? undefined}
                isFocused
                isSeriesExpanded
            />

            {series.map((part, index) => {
                const previous = series[index - 1] ?? commit;
                const previousLane = laneOf(previous);

                return isDeleted(part) ? (
                    <CommitTombstone
                        key={part.id}
                        commit={part}
                        previousLane={previousLane}
                    />
                ) : (
                    <CommitCard
                        key={part.id}
                        commit={part}
                        previousLane={previousLane}
                        isGroupedWithPrevious={isSameAuthor(previous, part)}
                        isSeriesExpanded
                    />
                );
            })}
        </>
    );
}

function isSameAuthor(
    previous: ThreadCommit | undefined,
    commit: Commit,
): boolean {
    return (
        previous !== undefined &&
        !isDeleted(previous) &&
        previous.user_id === commit.user_id
    );
}

Show.layout = AppLayout;
