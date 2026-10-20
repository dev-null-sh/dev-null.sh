import { Head, usePage } from '@inertiajs/react';
import { CommitComposer } from '@/components/commit-composer';
import { useCommitDialogClosedCount } from '@/components/commit-composer-dialog';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import AppLayout from '@/layouts/app-layout';
import type { Commit, CursorPaginated, Section } from '@/types';

export default function Show({
    section,
    commits,
}: {
    section: Section;
    commits: CursorPaginated<Commit>;
}) {
    const commitDialogClosedCount = useCommitDialogClosedCount();
    const { auth } = usePage().props;

    return (
        <>
            <Head title={section.title} />

            <header className="sticky top-0 z-10 flex h-14 items-center border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">{section.title}</h1>
            </header>

            {auth.user !== null && (
                <CommitComposer key={commitDialogClosedCount} />
            )}

            <CommitFeed
                commits={commits}
                end="That's the oldest one. There's nothing before it."
                empty={
                    <EmptyFeed command={`fatal: nothing in ${section.value}`}>
                        Nobody has committed anything here yet. Be the first.
                    </EmptyFeed>
                }
            />
        </>
    );
}

Show.layout = AppLayout;
