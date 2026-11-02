import { Head } from '@inertiajs/react';
import { SearchResults } from '@/components/commit-search-results';
import AppLayout from '@/layouts/app-layout';
import type { Commit } from '@/types';

export default function Show({
    name,
    summary,
    commits,
}: {
    name: string;
    summary: string | null;
    commits: Commit[];
}) {
    return (
        <>
            <Head title={`${name} · Hot topics`} />
            <header className="border-b px-4 py-6 sm:px-6">
                <p className="mb-2 font-mono text-xs text-muted-foreground">
                    Hot topic
                </p>
                <h1 className="text-2xl font-semibold tracking-tight wrap-break-word">
                    {name}
                </h1>
                {summary !== null && (
                    <div className="mt-4 space-y-2">
                        <p className="text-[15px] leading-relaxed wrap-break-word whitespace-pre-line">
                            {summary}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            AI recap of recent public commits. It may make
                            mistakes.
                        </p>
                    </div>
                )}
            </header>
            <SearchResults
                results={{ term: name, commits }}
                heading="What nullers have been saying"
                empty="No matching commits from the past week."
            />
        </>
    );
}

Show.layout = AppLayout;
