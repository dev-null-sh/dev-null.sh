import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowUpRight, Download, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ExportController from '@/actions/App/Http/Controllers/ExportController';
import UserProfileController from '@/actions/App/Http/Controllers/UserProfileController';
import ActivityGraphEmbed from '@/components/activity-graph-embed';
import DeleteUser from '@/components/delete-user';
import { GitHubProfile } from '@/components/github-profile';
import Heading from '@/components/heading';
import LanePicker from '@/components/lane-picker';
import { PasteisModeSetting } from '@/components/pasteis-mode-setting';
import { RemotesSetting } from '@/components/remotes-setting';
import TimezonePicker from '@/components/timezone-picker';
import TranslationLanguageSetting from '@/components/translation-language-setting';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useNow } from '@/hooks/use-now';
import { useUser } from '@/hooks/use-user';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    clearGifProviderKey,
    useGifProviderKey,
    writeGifProviderKey,
} from '@/lib/gif-provider-key';
import { gifProviderFor, gifProviders } from '@/lib/gif-providers';
import type { GifProviderId } from '@/lib/gif-providers';
import type { TranslationLanguage } from '@/lib/translation-languages';
import { formatFullDateTime, formatSyncedAgo } from '@/lib/utils';
import { show } from '@/routes/user';
import type { Lane } from '@/types';

export default function Edit({
    translationLanguage,
    translationLanguages,
    preferredLane,
    automaticLane,
    githubSyncedAt,
    activityGraphUrl,
    profileUrl,
}: {
    translationLanguage: string | null;
    translationLanguages: TranslationLanguage[];
    preferredLane: Lane | null;
    automaticLane: number;
    githubSyncedAt: string | null;
    activityGraphUrl: string;
    profileUrl: string;
}) {
    const { user } = useUser();
    const now = useNow();

    return (
        <>
            <Head title="Profile settings" />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Profile information"
                    description="Mirrored from GitHub when you sign in. To change any of it, change it on GitHub, then sync it here."
                />

                <GitHubProfile />

                <div className="mt-5 flex flex-wrap gap-2">
                    <Button asChild>
                        <a
                            href="https://github.com/settings/profile"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Edit on GitHub
                            <ArrowUpRight />
                        </a>
                    </Button>
                    <Form
                        {...UserProfileController.update.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => router.flushAll()}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                                aria-busy={processing}
                                data-test="sync-profile-button"
                            >
                                {processing ? <Spinner /> : <RefreshCw />}
                                Sync from GitHub
                            </Button>
                        )}
                    </Form>
                    <Button variant="outline" asChild>
                        <Link href={show(user)}>View profile</Link>
                    </Button>
                </div>

                <p
                    className="mt-3 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {githubSyncedAt ? (
                        <>
                            Last synced{' '}
                            <time
                                dateTime={githubSyncedAt}
                                title={formatFullDateTime(githubSyncedAt)}
                            >
                                {formatSyncedAgo(githubSyncedAt, now)}
                            </time>
                        </>
                    ) : (
                        'Last synced when you signed in'
                    )}
                </p>
            </section>

            <LanePicker
                preferredLane={preferredLane}
                automaticLane={automaticLane}
            />

            <TimezonePicker />

            <TranslationLanguageSetting
                language={translationLanguage}
                languages={translationLanguages}
            />

            <PasteisModeSetting />

            <GifKeySetting />

            <RemotesSetting />

            <ActivityGraphEmbed
                activityGraphUrl={activityGraphUrl}
                profileUrl={profileUrl}
            />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Export your data"
                    description="Download everything we store about you: your profile, commits and replies (including pending, rejected, and deleted ones), stars, follows, mutes, blocks, the messages you sent, notifications, SSH key fingerprints, and active sessions."
                />
                <p className="mb-5 text-sm text-muted-foreground">
                    Other people's replies are excluded. Your export is a JSON
                    file. Your profile is still managed on GitHub.
                </p>
                <Button variant="outline" asChild>
                    <a href={ExportController.url()}>
                        <Download />
                        Download JSON
                    </a>
                </Button>
            </section>

            <DeleteUser />
        </>
    );
}

Edit.layout = [AppLayout, SettingsLayout];

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
