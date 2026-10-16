import { Head, InfiniteScroll, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ModerationController from '@/actions/App/Http/Controllers/ModerationController';
import { CommitDecisionItem } from '@/components/commit-decision-item';
import { EmptyFeed } from '@/components/commit-feed';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { CursorPaginated, LoggedCommitDecision } from '@/types';

export default function Decisions({
    decisions,
}: {
    decisions: CursorPaginated<LoggedCommitDecision>;
}) {
    return (
        <>
            <Head title="Moderation decisions" />

            <div className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4">
                <Link
                    href={ModerationController.show()}
                    aria-label="Back to the queue"
                    className="text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </Link>
                <h1 className="font-semibold">Decisions</h1>
                <p className="ml-auto text-sm text-muted-foreground">
                    Newest first
                </p>
            </div>

            {decisions.data.length === 0 ? (
                <div className="px-6 py-16 text-center sm:px-12">
                    <EmptyFeed command="$ git log --grep=moderated">
                        No commits have been approved or rejected yet.
                    </EmptyFeed>
                </div>
            ) : (
                <InfiniteScroll
                    data="decisions"
                    onlyNext
                    preserveUrl
                    loading={
                        <div className="flex justify-center py-6">
                            <Spinner className="text-muted-foreground" />
                        </div>
                    }
                >
                    <ul className="divide-y">
                        {decisions.data.map((decision) => (
                            <CommitDecisionItem
                                key={decision.id}
                                decision={decision}
                            />
                        ))}
                    </ul>
                </InfiniteScroll>
            )}
        </>
    );
}

Decisions.layout = AppLayout;
