import { router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import RepoContributorCandidateController from '@/actions/App/Http/Controllers/RepoContributorCandidateController';
import RepoContributorController from '@/actions/App/Http/Controllers/RepoContributorController';
import { MemberRow, PeoplePicker } from '@/components/chat/org';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Candidate } from '@/lib/chat';
import type { Repo, RepoContributor, RepoRole, User } from '@/types';

type Visit =
    | ReturnType<typeof RepoContributorController.store>
    | ReturnType<typeof RepoContributorController.update>
    | ReturnType<typeof RepoContributorController.destroy>;

/**
 * Settles once the visit finishes, so callers can show that it's in flight. The first error comes back as the reason.
 */
function visit(request: Visit, data: Record<string, string | string[]> = {}) {
    return new Promise<void>((resolve, reject) => {
        let failure: string | null = null;

        router.visit(request, {
            data,
            preserveScroll: true,
            onError: (errors) => {
                failure = Object.values(errors)[0] ?? 'Something went wrong.';
            },
            onFinish: () =>
                failure === null ? resolve() : reject(new Error(failure)),
        });
    });
}

function toastFailure(error: unknown) {
    toast.error(
        error instanceof Error ? error.message : 'Something went wrong.',
    );
}

async function searchCandidates(
    repo: Repo,
    term: string,
    signal: AbortSignal,
): Promise<Candidate[]> {
    const response = await fetch(
        RepoContributorCandidateController.index.url(repo, {
            query: { q: term },
        }),
        { headers: { Accept: 'application/json' }, cache: 'no-store', signal },
    );

    if (!response.ok) {
        return [];
    }

    const { candidates } = (await response.json()) as {
        candidates: Candidate[];
    };

    return candidates.toSorted(
        (a, b) => Number(a.reason !== null) - Number(b.reason !== null),
    );
}

/**
 * Everyone in the repo with their role. Maintainers add and remove people, and promote or demote them; anyone can
 * leave.
 */
export function RepoContributorsDialog({
    repo,
    role,
    contributors,
    user,
    open,
    onOpenChange,
}: {
    repo: Repo;
    role: RepoRole;
    contributors: RepoContributor[];
    user: User;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isMaintainer = role === 'maintainer';
    const [isAdding, setIsAdding] = useState(false);
    const [isConfirmingLeave, setIsConfirmingLeave] = useState(false);
    const maintainers = contributors.filter(
        (contributor) => contributor.role === 'maintainer',
    ).length;

    const target = (person: User) => ({
        repo: repo.slug,
        contributor: person.username,
    });

    const leave = () =>
        visit(RepoContributorController.destroy(target(user))).catch(
            toastFailure,
        );

    const run = (change: Promise<void>) =>
        change.catch((error: unknown) => {
            toastFailure(error);
        });

    return (
        <>
            <Dialog
                open={open && !isAdding}
                onOpenChange={(isOpen) => {
                    setIsConfirmingLeave(false);
                    onOpenChange(isOpen);
                }}
            >
                <DialogContent
                    className="flex max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-md"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        (event.currentTarget as HTMLElement).focus();
                    }}
                    data-test="repo-contributors"
                >
                    <div className="space-y-1 border-b px-5 pt-5 pb-4">
                        <DialogTitle
                            className="truncate pr-6"
                            title={repo.name}
                        >
                            {repo.name}
                        </DialogTitle>
                        <DialogDescription className="font-mono text-xs tabular-nums">
                            {contributors.length}{' '}
                            {contributors.length === 1
                                ? 'contributor'
                                : 'contributors'}
                        </DialogDescription>
                    </div>

                    <ul className="min-h-0 flex-1 overflow-y-auto overscroll-contain py-2">
                        {contributors.map((contributor) => (
                            <MemberRow
                                key={contributor.user.id}
                                member={contributor}
                                user={user}
                                canManage={
                                    isMaintainer &&
                                    contributor.user.id !== user.id
                                }
                                isLastMaintainer={
                                    contributor.role === 'maintainer' &&
                                    maintainers === 1
                                }
                                removeLabel="Remove from repo"
                                onRoleChange={(next) =>
                                    run(
                                        visit(
                                            RepoContributorController.update(
                                                target(contributor.user),
                                            ),
                                            { role: next },
                                        ),
                                    )
                                }
                                onRemove={() =>
                                    run(
                                        visit(
                                            RepoContributorController.destroy(
                                                target(contributor.user),
                                            ),
                                        ),
                                    )
                                }
                            />
                        ))}
                    </ul>

                    <div className="flex items-center gap-2 border-t px-5 py-3">
                        {isMaintainer && (
                            <Button
                                variant="outline"
                                onClick={() => setIsAdding(true)}
                                data-test="repo-add-people"
                            >
                                <UserPlus className="size-4" />
                                Add people
                            </Button>
                        )}
                        <Button
                            variant={
                                isConfirmingLeave ? 'destructive' : 'ghost'
                            }
                            className="ml-auto min-w-28"
                            onClick={() =>
                                isConfirmingLeave
                                    ? void leave()
                                    : setIsConfirmingLeave(true)
                            }
                            data-test="repo-leave"
                        >
                            {isConfirmingLeave
                                ? 'Leave for good'
                                : 'Leave repo'}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            {isMaintainer && (
                <RepoPeopleDialog
                    repo={repo}
                    open={open && isAdding}
                    onOpenChange={setIsAdding}
                />
            )}
        </>
    );
}

/**
 * Picks people to add to a repo. Only the maintainer's followers can join, so everyone else stays in the list,
 * greyed out, with the reason why.
 */
export function RepoPeopleDialog({
    repo,
    open,
    onOpenChange,
}: {
    repo: Repo;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [picked, setPicked] = useState<User[]>([]);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const reset = () => {
        setPicked([]);
        setError(null);
    };

    const toggle = (person: User) => {
        setError(null);
        setPicked((people) =>
            people.some((item) => item.id === person.id)
                ? people.filter((item) => item.id !== person.id)
                : [...people, person],
        );
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (picked.length === 0) {
            return;
        }

        setIsSaving(true);

        try {
            await visit(RepoContributorController.store(repo), {
                contributors: picked.map((person) => person.username),
            });

            reset();
            onOpenChange(false);
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : 'Something went wrong.',
            );
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    reset();
                }

                onOpenChange(isOpen);
            }}
        >
            <DialogContent
                className="flex max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-md"
                data-test="repo-people"
            >
                <form
                    onSubmit={(event) => void submit(event)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <PeoplePicker
                        search={(term, signal) =>
                            searchCandidates(repo, term, signal)
                        }
                        picked={picked}
                        onToggle={toggle}
                        header={
                            <div className="space-y-1">
                                <DialogTitle>Add people</DialogTitle>
                                <DialogDescription>
                                    They join as contributors. Only people who
                                    follow you can be added.
                                </DialogDescription>
                            </div>
                        }
                    />

                    <div className="flex items-center gap-3 border-t px-5 py-3">
                        <p
                            role="alert"
                            className="min-w-0 flex-1 truncate font-mono text-xs text-destructive"
                            title={error ?? undefined}
                        >
                            {error}
                        </p>
                        <Button
                            type="submit"
                            className="min-w-28"
                            disabled={isSaving || picked.length === 0}
                            data-test="repo-people-submit"
                        >
                            {`Invite ${picked.length || ''}`.trim()}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
