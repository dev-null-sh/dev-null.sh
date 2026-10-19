import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, UserPlus } from 'lucide-react';
import { useState } from 'react';
import RepoController from '@/actions/App/Http/Controllers/RepoController';
import { MemberStack } from '@/components/chat/org';
import { CommitComposer } from '@/components/commit-composer';
import { CommitFeed, EmptyFeed } from '@/components/commit-feed';
import {
    RepoContributorsDialog,
    RepoPeopleDialog,
} from '@/components/repo-contributors';
import { Button } from '@/components/ui/button';
import { MaintainedRepoContext } from '@/hooks/use-commit-deletion';
import AppLayout from '@/layouts/app-layout';
import type {
    Commit,
    CursorPaginated,
    Repo,
    RepoContributor,
    RepoRole,
} from '@/types';

export default function Show({
    repo,
    role,
    contributors,
    commits,
}: {
    repo: Repo;
    role: RepoRole;
    contributors: RepoContributor[];
    commits: CursorPaginated<Commit>;
}) {
    const isMaintainer = role === 'maintainer';

    return (
        <>
            <Head title={repo.name} />

            <header className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-card px-4 sm:px-6">
                <Link
                    href={RepoController.index()}
                    aria-label="Back to repos"
                    className="-ml-2 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <ArrowLeft className="size-5" />
                </Link>
                <ContributorsButton
                    repo={repo}
                    role={role}
                    contributors={contributors}
                />
                {isMaintainer && <InviteContributors repo={repo} />}
            </header>

            {repo.description && (
                <p className="border-b px-4 py-3 text-sm text-pretty text-muted-foreground sm:px-6">
                    {repo.description}
                </p>
            )}

            <CommitComposer key={repo.id} id="repo-body" repo={repo} />

            <MaintainedRepoContext value={isMaintainer ? repo.id : null}>
                <CommitFeed
                    commits={commits}
                    end="That's the root commit of this repo."
                    empty={
                        <EmptyFeed command="fatal: your current branch 'main' does not have any commits yet">
                            Only the contributors of {repo.name} will see what
                            you commit here.
                        </EmptyFeed>
                    }
                />
            </MaintainedRepoContext>
        </>
    );
}

function ContributorsButton({
    repo,
    role,
    contributors,
}: {
    repo: Repo;
    role: RepoRole;
    contributors: RepoContributor[];
}) {
    const {
        props: { auth },
    } = usePage();
    const [isShowing, setIsShowing] = useState(false);

    if (!auth.user) {
        return null;
    }

    const user = auth.user;

    return (
        <>
            <button
                type="button"
                onClick={() => setIsShowing(true)}
                className="flex min-w-0 flex-1 items-center gap-3 rounded-lg text-left"
                aria-label={`Contributors of ${repo.name}`}
                data-test="repo-contributors-button"
            >
                <MemberStack
                    users={contributors
                        .map((contributor) => contributor.user)
                        .toSorted(
                            (a, b) =>
                                Number(a.id === user.id) -
                                Number(b.id === user.id),
                        )}
                    max={4}
                    avatarClassName="size-8"
                />
                <span className="min-w-0">
                    <span
                        className="block truncate text-[15px] font-semibold hover:underline"
                        title={repo.name}
                    >
                        {repo.name}
                    </span>
                    <span className="block truncate font-mono text-xs text-muted-foreground tabular-nums">
                        repo · {contributors.length}{' '}
                        {contributors.length === 1
                            ? 'contributor'
                            : 'contributors'}
                    </span>
                </span>
            </button>
            <RepoContributorsDialog
                repo={repo}
                role={role}
                contributors={contributors}
                user={user}
                open={isShowing}
                onOpenChange={setIsShowing}
            />
        </>
    );
}

/**
 * Only maintainers can add people, so only they get the shortcut.
 */
function InviteContributors({ repo }: { repo: Repo }) {
    const [isInviting, setIsInviting] = useState(false);

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                className="-mr-1 shrink-0"
                onClick={() => setIsInviting(true)}
                data-test="invite-contributors"
            >
                <UserPlus className="size-4" />
                Invite contributors
            </Button>
            <RepoPeopleDialog
                repo={repo}
                open={isInviting}
                onOpenChange={setIsInviting}
            />
        </>
    );
}

Show.layout = AppLayout;
