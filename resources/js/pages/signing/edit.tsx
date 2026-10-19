import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import PasskeyController from '@/actions/App/Http/Controllers/PasskeyController';
import SigningController from '@/actions/App/Http/Controllers/SigningController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useNow } from '@/hooks/use-now';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    confirmSigning,
    createPasskey,
    supportsPasskeys,
} from '@/lib/passkeys';
import { formatRelativeTime } from '@/lib/utils';

type Passkey = {
    id: number;
    name: string;
    created_at: string;
    last_used_at: string | null;
};

export default function Edit({
    passkeys,
    requiresSignedCommits,
}: {
    passkeys: Passkey[];
    requiresSignedCommits: boolean;
}) {
    const { errors } = usePage().props;
    const now = useNow();
    const [name, setName] = useState('1Password');
    const [busy, setBusy] = useState<string | null>(null);

    const withPasskey = async (key: string, run: () => Promise<void>) => {
        setBusy(key);

        try {
            await run();
        } catch (error) {
            toast.error(
                error instanceof Error && error.name !== 'NotAllowedError'
                    ? error.message
                    : 'The passkey did not confirm, so nothing changed.',
            );
        } finally {
            setBusy(null);
        }
    };

    const add = () =>
        withPasskey('add', async () => {
            const signature = requiresSignedCommits
                ? await confirmSigning('add')
                : null;
            const credential = await createPasskey();

            router.post(
                PasskeyController.store().url,
                { name, credential, ...(signature && { signature }) },
                { preserveScroll: true },
            );
        });

    const remove = (passkey: Passkey) =>
        withPasskey(`remove:${passkey.id}`, async () => {
            const signature = requiresSignedCommits
                ? await confirmSigning(`remove:${passkey.id}`)
                : null;

            router.delete(PasskeyController.destroy(passkey.id).url, {
                data: signature ? { signature } : {},
                preserveScroll: true,
            });
        });

    const toggle = () =>
        withPasskey('toggle', async () => {
            const enabled = !requiresSignedCommits;
            const signature = await confirmSigning(
                enabled ? 'enable' : 'disable',
            );

            router.put(
                SigningController.update().url,
                { enabled, signature },
                { preserveScroll: true },
            );
        });

    if (!supportsPasskeys()) {
        return (
            <section className="px-4 py-6 sm:px-6">
                <p className="text-sm text-muted-foreground">
                    This browser can't use passkeys.
                </p>
            </section>
        );
    }

    return (
        <>
            <Head title="Signing settings" />

            <section className="px-4 py-6 sm:px-6">
                <header className="mb-5">
                    <h2 className="text-lg font-semibold tracking-tight">
                        Signed commits
                    </h2>
                    <p className="mt-1 max-w-prose text-sm text-pretty text-muted-foreground">
                        Sign every commit you post on the web with a passkey,
                        such as one in 1Password. Someone with your session or
                        your GitHub account then can't post as you, and can't
                        turn this off. While it is on, the terminal and the app
                        can't post for you.
                    </p>
                </header>

                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3">
                    <p className="text-sm">
                        {requiresSignedCommits ? (
                            <>
                                <span className="font-mono text-[13px]">
                                    on
                                </span>{' '}
                                · every web commit needs your passkey
                            </>
                        ) : (
                            <>
                                <span className="font-mono text-[13px]">
                                    off
                                </span>{' '}
                                · commits post without a signature
                            </>
                        )}
                    </p>
                    <Button
                        variant={requiresSignedCommits ? 'outline' : 'default'}
                        size="sm"
                        disabled={
                            busy !== null ||
                            (!requiresSignedCommits && passkeys.length === 0)
                        }
                        onClick={() => void toggle()}
                    >
                        {busy === 'toggle'
                            ? 'Waiting for passkey…'
                            : requiresSignedCommits
                              ? 'Stop requiring'
                              : 'Require signed commits'}
                    </Button>
                </div>
                <InputError
                    message={errors.signature ?? errors.enabled}
                    className="mt-2"
                />
            </section>

            <section className="px-4 py-6 sm:px-6">
                <h3 className="mb-3 font-semibold tracking-tight">Passkeys</h3>

                {passkeys.length === 0 ? (
                    <p className="mb-4 rounded-lg border px-4 py-6 text-center font-mono text-[13px] text-muted-foreground">
                        fatal: no passkeys yet
                    </p>
                ) : (
                    <ul className="mb-4 overflow-hidden rounded-lg border">
                        {passkeys.map((passkey) => (
                            <li
                                key={passkey.id}
                                className="flex items-center gap-3 border-b px-4 py-3 last:border-b-0"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        {passkey.name}
                                    </p>
                                    <p className="truncate font-mono text-[13px] text-muted-foreground">
                                        added{' '}
                                        {formatRelativeTime(
                                            passkey.created_at,
                                            now,
                                        )}
                                        {' · '}
                                        {passkey.last_used_at === null
                                            ? 'not used yet'
                                            : `last used ${formatRelativeTime(passkey.last_used_at, now)}`}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy !== null}
                                    onClick={() => void remove(passkey)}
                                    className="hover:bg-destructive/10 hover:text-destructive"
                                >
                                    {busy === `remove:${passkey.id}`
                                        ? 'Waiting…'
                                        : 'Remove'}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <input
                        type="text"
                        value={name}
                        maxLength={100}
                        onChange={(event) => setName(event.target.value)}
                        aria-label="Passkey name"
                        className="h-8 w-60 max-w-full rounded-md border bg-transparent px-2 text-sm"
                    />
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={busy !== null || name.trim() === ''}
                        onClick={() => void add()}
                    >
                        {busy === 'add'
                            ? 'Waiting for passkey…'
                            : 'Add passkey'}
                    </Button>
                </div>
                <InputError message={errors.credential} className="mt-2" />
            </section>
        </>
    );
}

Edit.layout = [AppLayout, SettingsLayout];
