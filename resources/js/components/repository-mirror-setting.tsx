import { useForm } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

export type RepositoryMirrorSummary = {
    id: string;
    repository: string;
    private: boolean;
    branch: string;
    mirror_commits: boolean;
    last_synced_at: string | null;
    last_error: string | null;
};

function RepositoryConnection({
    mirror,
}: {
    mirror: RepositoryMirrorSummary | null;
}) {
    const [editing, setEditing] = useState(!mirror);
    const form = useForm({
        repository: mirror?.repository ?? '',
        token: '',
        mirror_commits: mirror?.mirror_commits ?? true,
    });
    const disconnect = useForm({});
    return (
        <div className="border-t py-4">
            {mirror && (
                <div className="mt-4 space-y-2 text-sm">
                    <p className="font-medium break-all">
                        {mirror.private && (
                            <LockKeyhole
                                aria-label="Private repository"
                                className="mr-1.5 inline size-3.5"
                            />
                        )}
                        {mirror.repository}{' '}
                        <span className="font-normal text-muted-foreground">
                            · {mirror.private ? 'Private' : 'Public'} ·{' '}
                            {mirror.branch}
                        </span>
                    </p>
                    <p className="text-muted-foreground">
                        {mirror.mirror_commits
                            ? 'New approved commits and replies are mirrored automatically.'
                            : 'Automatic commit mirroring is off.'}{' '}
                        Choose where each reaction goes in its picker.
                    </p>
                    <p
                        role="status"
                        className={
                            mirror.last_error
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        }
                    >
                        {mirror.last_error ??
                            (mirror.last_synced_at
                                ? `Last delivered ${new Date(mirror.last_synced_at).toLocaleString()}.`
                                : 'Connected. Waiting for your next activity.')}
                    </p>
                    {!editing && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setEditing(true)}
                            >
                                Update connection
                            </Button>
                            <Button
                                variant="ghost"
                                disabled={disconnect.processing}
                                onClick={() =>
                                    disconnect.delete(
                                        `/settings/repository/${mirror.id}`,
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                form.reset('token');
                                                setEditing(true);
                                            },
                                        },
                                    )
                                }
                            >
                                Disconnect
                            </Button>
                        </div>
                    )}
                </div>
            )}
            {editing && (
                <form
                    className="mt-4 space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/settings/repository', {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset('token');
                                setEditing(false);
                            },
                            onError: () => form.reset('token'),
                        });
                    }}
                >
                    <label className="block text-sm font-medium">
                        Repository
                        <input
                            name="repository"
                            required
                            placeholder="you/dev-null-archive"
                            autoComplete="off"
                            value={form.data.repository}
                            onChange={(event) =>
                                form.setData('repository', event.target.value)
                            }
                            className="mt-1.5 block h-10 w-full rounded-md border bg-background px-3 font-mono text-sm focus-visible:outline-2 focus-visible:outline-ring"
                        />
                        {form.errors.repository && (
                            <span
                                role="alert"
                                className="mt-1 block text-xs text-destructive"
                            >
                                {form.errors.repository}
                            </span>
                        )}
                    </label>
                    <label className="block text-sm font-medium">
                        Fine-grained GitHub token
                        <input
                            name="token"
                            type="password"
                            required
                            autoComplete="new-password"
                            value={form.data.token}
                            onChange={(event) =>
                                form.setData('token', event.target.value)
                            }
                            className="mt-1.5 block h-10 w-full rounded-md border bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                        />
                        {form.errors.token && (
                            <span
                                role="alert"
                                className="mt-1 block text-xs text-destructive"
                            >
                                {form.errors.token}
                            </span>
                        )}
                    </label>
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        Create a token for your signed-in GitHub account with
                        access to only this repository and Contents: read and
                        write. Initialize the repo with a README first. The
                        token is encrypted on the server.
                    </p>
                    <label className="flex items-start gap-2 text-sm">
                        <Checkbox
                            checked={form.data.mirror_commits}
                            onCheckedChange={(checked) =>
                                form.setData('mirror_commits', checked === true)
                            }
                            className="mt-0.5"
                        />
                        Mirror my new approved commits and replies
                    </label>
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        A public repository makes its copies public, even for
                        reactions hidden on dev-null. Removing a reaction writes
                        a removal record; Git history keeps earlier copies.
                        Disconnecting cancels pending copies to that
                        destination. Existing activity is not backfilled.
                    </p>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Checking repository…'
                                : mirror
                                  ? 'Update repository'
                                  : 'Connect repository'}
                        </Button>
                        {mirror && (
                            <Button
                                variant="ghost"
                                type="button"
                                onClick={() => {
                                    form.reset('token');
                                    setEditing(false);
                                }}
                            >
                                Cancel
                            </Button>
                        )}
                    </div>
                </form>
            )}
        </div>
    );
}

export function RepositoryMirrorSetting({
    mirrors,
}: {
    mirrors: RepositoryMirrorSummary[];
}) {
    const [adding, setAdding] = useState(mirrors.length === 0);
    return (
        <section id="repository" className="scroll-mt-6 px-4 py-6 sm:px-6">
            <h2 className="text-base font-semibold">Your repositories</h2>
            <p className="mt-2 mb-4 text-sm text-muted-foreground">
                Keep your commits and reactions in the GitHub repos you choose.
                Select destinations in the reaction picker, then use GitHub
                Actions in each repo for automations.
            </p>
            {mirrors.map((mirror) => (
                <RepositoryConnection key={mirror.id} mirror={mirror} />
            ))}
            {adding ? (
                <RepositoryConnection key={mirrors.length} mirror={null} />
            ) : (
                <Button variant="outline" onClick={() => setAdding(true)}>
                    Add repository
                </Button>
            )}
        </section>
    );
}
