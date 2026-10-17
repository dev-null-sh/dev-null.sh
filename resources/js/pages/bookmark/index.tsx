import { useLang } from '@erag/lang-sync-inertia/react';
import { Head, Link, router } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import { useRef } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { BookmarkRemovalContext } from '@/components/bookmark-button';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import { focusCommit } from '@/hooks/use-keyboard-shortcuts';
import AppLayout from '@/layouts/app-layout';
import type { Commit, CursorPaginated } from '@/types';

export default function Index({
    commits,
}: {
    commits: CursorPaginated<Commit>;
}) {
    const { __ } = useLang();
    const headingRef = useRef<HTMLHeadingElement>(null);
    const remove = (id: string) => {
        const index = commits.data.findIndex((commit) => commit.id === id);

        if (index === -1) {
            return;
        }

        const next = commits.data[index + 1] ?? commits.data[index - 1];
        const current = document.querySelector(`[data-commit="${id}"]`);

        if (current?.contains(document.activeElement)) {
            const target = next
                ? document.querySelector<HTMLElement>(
                      `[data-commit="${next.id}"]`,
                  )
                : headingRef.current;

            if (target) {
                focusCommit(target);
            }
        }

        if (commits.data.length === 1 && commits.next_cursor !== null) {
            router.reload({ only: ['commits'], reset: ['commits'] });
        } else {
            router.replaceProp('commits.data', (data: Commit[]) =>
                data.filter((commit) => commit.id !== id),
            );
        }
    };

    return (
        <>
            <Head title={__('users.saved.title')} />

            <header className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 ref={headingRef} tabIndex={-1} className="font-semibold">
                    {__('users.saved.title')}
                </h1>
                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <LockKeyhole aria-hidden className="size-3.5" />
                    {__('users.saved.only_you')}
                </span>
            </header>

            <p className="border-b px-4 py-3 text-sm text-muted-foreground sm:px-6">
                {__('users.saved.description')}
            </p>

            <BookmarkRemovalContext value={remove}>
                <CommitFeed
                    commits={commits}
                    end={__('users.saved.end')}
                    empty={
                        <EmptyFeed command={__('users.saved.empty_command')}>
                            {__('users.saved.empty')}
                            <Link
                                href={LogController.show('trending')}
                                className="mt-3 block text-primary hover:underline"
                            >
                                {__('users.saved.explore')}
                            </Link>
                        </EmptyFeed>
                    }
                />
            </BookmarkRemovalContext>
        </>
    );
}

Index.layout = AppLayout;
