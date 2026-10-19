import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, FolderGit2 } from 'lucide-react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import RepoController from '@/actions/App/Http/Controllers/RepoController';
import { CommitCard, CommitTombstone } from '@/components/commit-card';
import { CopyLinkButton } from '@/components/copy-link-button';
import AppLayout from '@/layouts/app-layout';
import { isDeleted, laneOf, shortHash } from '@/lib/utils';
import { home } from '@/routes';
import type { Commit, CursorPaginated, Repo, ThreadCommit } from '@/types';

const titleLength = 60;

export default function Show({
    commit,
    ancestors,
    replies,
    repo,
}: {
    commit: Commit;
    ancestors: ThreadCommit[];
    replies: CursorPaginated<ThreadCommit>;
    repo: Pick<Repo, 'id' | 'name' | 'slug'> | null;
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

            {repo && (
                <Link
                    href={RepoController.show(repo)}
                    className="flex min-w-0 items-center gap-2 border-b px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    data-test="commit-repo"
                >
                    <FolderGit2 aria-hidden className="size-4 shrink-0" />
                    <span className="truncate" title={repo.name}>
                        In{' '}
                        <span className="font-medium text-foreground">
                            {repo.name}
                        </span>
                        {' · '}contributors only
                    </span>
                </Link>
            )}

            {ancestors.map((ancestor, index) => {
                const previous = ancestors[index - 1];
                const previousLane = previous ? laneOf(previous) : undefined;

                return isDeleted(ancestor) ? (
                    <CommitTombstone
                        key={ancestor.id}
                        previousLane={previousLane}
                    />
                ) : (
                    <CommitCard
                        key={ancestor.id}
                        commit={ancestor}
                        previousLane={previousLane}
                        isGroupedWithPrevious={isSameAuthor(previous, ancestor)}
                        canDelete={false}
                    />
                );
            })}

            <CommitCard
                key={commit.id}
                commit={commit}
                previousLane={lastAncestor ? laneOf(lastAncestor) : undefined}
                isGroupedWithPrevious={isSameAuthor(lastAncestor, commit)}
                initialReplies={replies}
                endsWithDot
                isFocused
            />
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
