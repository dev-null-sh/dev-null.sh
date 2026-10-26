import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserCommitVisibilityController';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';

const channels = [
    {
        id: 'cli',
        label: 'Show terminal commits',
        description: 'Commits posted through the terminal client.',
    },
    {
        id: 'ci',
        label: 'Show workflow commits',
        description:
            'Commits posted by CI workflows, including GitHub Actions.',
    },
    {
        id: 'ai',
        label: 'Show AI-generated commits',
        description: 'Declared AI content posted on a developer’s behalf.',
    },
];

export default function Edit({
    hiddenCommitChannels,
}: {
    hiddenCommitChannels: string[];
}) {
    const [processing, setProcessing] = useState(false);
    const failed = () => {
        toast.error('Could not save commit visibility. Please try again.');

        return false;
    };

    const toggle = (channel: string) => {
        if (processing) return;

        const hidden = hiddenCommitChannels.includes(channel)
            ? hiddenCommitChannels.filter((value) => value !== channel)
            : [...hiddenCommitChannels, channel];

        router.put(
            update.url(),
            { hidden_commit_channels: hidden },
            {
                preserveScroll: true,
                only: ['hiddenCommitChannels'],
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: failed,
                onHttpException: failed,
                onNetworkError: failed,
                onSuccess: () => router.flushAll(),
            },
        );
    };

    return (
        <>
            <Head title="Commit visibility settings" />

            <section className="px-4 py-6 sm:px-6">
                <Heading
                    title="Commit visibility"
                    description="Choose which commits you see. These settings follow your account across devices."
                />
                <p className="text-sm text-muted-foreground">
                    Your own commits stay visible. Hidden replies keep their
                    place in a thread, and you can show them individually.
                </p>
            </section>

            {channels.map((channel) => {
                const enabled = !hiddenCommitChannels.includes(channel.id);

                return (
                    <section
                        key={channel.id}
                        className="flex items-start justify-between gap-4 px-4 py-5 sm:px-6"
                    >
                        <div className="min-w-0 space-y-2">
                            <div className="flex items-center gap-2">
                                <h2
                                    id={`${channel.id}-visibility-label`}
                                    className="text-sm font-medium"
                                >
                                    {channel.label}
                                </h2>
                                <span className="rounded border px-1 font-mono text-[11px] text-muted-foreground">
                                    {channel.id}
                                </span>
                            </div>
                            <p
                                id={`${channel.id}-visibility-description`}
                                className="text-sm text-muted-foreground"
                            >
                                {channel.description}
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={enabled}
                            aria-labelledby={`${channel.id}-visibility-label`}
                            aria-describedby={`${channel.id}-visibility-description`}
                            aria-disabled={processing}
                            onClick={() => toggle(channel.id)}
                            className="-mt-2 inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <span
                                className={cn(
                                    'inline-flex h-6 w-11 items-center rounded-full border border-transparent p-0.5 transition-colors',
                                    enabled
                                        ? 'bg-primary'
                                        : 'bg-muted-foreground/30',
                                )}
                            >
                                <span
                                    className={cn(
                                        'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                                        enabled && 'translate-x-5',
                                    )}
                                />
                            </span>
                        </button>
                    </section>
                );
            })}
        </>
    );
}

Edit.layout = [AppLayout, SettingsLayout];
