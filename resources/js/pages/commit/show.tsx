import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { CommitCard } from '@/components/commit-card';
import AppLayout from '@/layouts/app-layout';
import { laneFor, shortHash } from '@/lib/utils';
import type { Commit } from '@/types';

const titleLength = 60;

export default function Show({
    commit,
    ancestors,
    replies,
}: {
    commit: Commit;
    ancestors: Commit[];
    replies: Commit[];
}) {
    const excerpt =
        commit.body.length > titleLength
            ? `${commit.body.slice(0, titleLength).trimEnd()}…`
            : commit.body;

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        router.visit(LogController.show('following'));
    };

    const lastAncestor = ancestors.at(-1);

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
            </div>

            {ancestors.map((ancestor, index) => (
                <CommitCard
                    key={ancestor.id}
                    commit={ancestor}
                    previousLane={
                        index > 0
                            ? laneFor(ancestors[index - 1].user_id)
                            : undefined
                    }
                    canDelete={false}
                />
            ))}

            <CommitCard
                key={commit.id}
                commit={commit}
                previousLane={
                    lastAncestor ? laneFor(lastAncestor.user_id) : undefined
                }
                initialReplies={replies}
                isFocused
            />
        </>
    );
}

Show.layout = AppLayout;
