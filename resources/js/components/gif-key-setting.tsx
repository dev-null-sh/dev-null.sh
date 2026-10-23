import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useGifProviderKey } from '@/hooks/use-gif-provider-key';
import { useUser } from '@/hooks/use-user';
import {
    clearGifProviderKey,
    writeGifProviderKey,
} from '@/lib/gif-provider-key';
import { gifProviderFor, gifProviders } from '@/lib/gif-providers';
import type { GifProviderId } from '@/lib/gif-providers';

const helpHref: Record<GifProviderId, string> = {
    giphy: 'https://developers.giphy.com',
    klipy: 'https://partner.klipy.com',
};

export function GifKeySetting() {
    const { user } = useUser();
    const entry = useGifProviderKey(user.id);
    const [providerId, setProviderId] = useState<GifProviderId>(
        entry?.provider ?? 'klipy',
    );
    const [key, setKey] = useState('');
    const [editing, setEditing] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const displayProviderId: GifProviderId =
        editing || entry === null ? providerId : entry.provider;
    const provider = gifProviderFor(displayProviderId);
    const lastStored = useRef<{ provider?: GifProviderId; key?: string }>({
        provider: entry?.provider,
        key: entry?.key,
    });

    useEffect(() => {
        if (
            lastStored.current.provider !== entry?.provider ||
            lastStored.current.key !== entry?.key
        ) {
            lastStored.current = { provider: entry?.provider, key: entry?.key };
            setError(null);
        }
    }, [entry?.provider, entry?.key]);

    const connect = async () => {
        if (processing) {
            return;
        }

        const candidate = key.trim();

        if (candidate === '') {
            return;
        }

        if (candidate.length > 255) {
            setError('That key is too long.');

            return;
        }

        setProcessing(true);
        setError(null);

        const selected = gifProviderFor(providerId);
        const verdict = await selected.validateKey(candidate);

        if (verdict === 'invalid') {
            setError(`That key was rejected by ${selected.label}.`);
            setProcessing(false);

            return;
        }

        if (verdict === 'unreachable') {
            setError(`Couldn't reach ${selected.label} to verify the key.`);
            setProcessing(false);

            return;
        }

        if (
            !writeGifProviderKey(user.id, {
                provider: providerId,
                key: candidate,
            })
        ) {
            setError("Couldn't save the key in this browser.");
            setProcessing(false);

            return;
        }

        setKey('');
        setError(null);
        setEditing(false);
        setProcessing(false);
    };

    const remove = () => {
        clearGifProviderKey(user.id);
        setError(null);
        setEditing(false);
    };

    return (
        <section
            aria-labelledby="gif-key-label"
            aria-describedby="gif-key-description"
            className="px-4 py-6 sm:px-6"
        >
            <h2 id="gif-key-label" className="text-base font-semibold">
                GIFs
            </h2>
            <p
                id="gif-key-description"
                className="mt-2 text-sm text-muted-foreground"
            >
                GIF search runs on your own {provider.label} API key, under your
                own quota. Get a key at{' '}
                <a
                    href={helpHref[displayProviderId]}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="underline underline-offset-4"
                >
                    {provider.label}
                </a>{' '}
                and save it here. Without a key, the picker stays hidden.
            </p>
            <p
                className="mt-2 text-sm text-muted-foreground"
                data-test="gif-storage-note"
            >
                Stored in this browser only — never sent to the server. Each
                device needs its own key.
            </p>
            {entry !== null && !editing ? (
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <p className="text-sm" data-test="gif-connected">
                        Connected to {gifProviderFor(entry.provider).label}.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setProviderId(entry.provider);
                            setEditing(true);
                        }}
                        data-test="change-provider-button"
                    >
                        Change provider
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={remove}
                        data-test="gif-remove-button"
                    >
                        Remove
                    </Button>
                </div>
            ) : (
                <div className="mt-4 max-w-md space-y-3">
                    <div
                        role="group"
                        aria-label="GIF provider"
                        className="flex gap-2"
                    >
                        {Object.values(gifProviders).map((candidate) => (
                            <Button
                                key={candidate.id}
                                type="button"
                                size="sm"
                                variant={
                                    candidate.id === providerId
                                        ? 'default'
                                        : 'outline'
                                }
                                aria-pressed={candidate.id === providerId}
                                disabled={processing}
                                onClick={() => {
                                    setProviderId(candidate.id);
                                    setError(null);
                                }}
                                data-test={`provider-${candidate.id}`}
                            >
                                {candidate.label}
                            </Button>
                        ))}
                    </div>
                    <Label htmlFor="gif-key-input">
                        {provider.label} API key
                    </Label>
                    <div className="flex gap-2">
                        <input
                            id="gif-key-input"
                            value={key}
                            onChange={(event) => setKey(event.target.value)}
                            placeholder={`Paste your ${provider.label} API key`}
                            disabled={processing}
                            autoComplete="off"
                            spellCheck={false}
                            className="h-9 min-w-0 flex-1 rounded-md border bg-card px-3 text-base outline-none placeholder:text-muted-foreground focus-visible:border-primary md:text-sm"
                            data-test="gif-key-input"
                        />
                        <Button
                            type="button"
                            onClick={() => connect()}
                            disabled={processing || key.trim() === ''}
                            data-test="gif-connect-button"
                        >
                            {processing ? <Spinner /> : null}
                            {entry !== null || editing ? 'Update' : 'Connect'}
                        </Button>
                    </div>
                </div>
            )}
            {error !== null && (
                <p
                    className="mt-3 text-sm text-destructive"
                    role="alert"
                    data-test="gif-key-error"
                >
                    {error}
                </p>
            )}
        </section>
    );
}
