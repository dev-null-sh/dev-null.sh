import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserRemotesController';
import {
    useRemoteAlertsEnabled,
    useRemoteNotificationSupport,
} from '@/hooks/use-chat';
import type { RemoteAlertChannel } from '@/lib/remote-notifications';
import { setRemoteAlerts } from '@/lib/remote-notifications';
import { RemotePushValidationError } from '@/lib/remote-push';
import { cn } from '@/lib/utils';

/**
 * Remotes are on by default. Turning them off hides the Remotes page and stops anyone messaging the user.
 */
export function RemotesSetting() {
    const { auth } = usePage().props;
    const enabled = auth.remotes !== null;
    const [processing, setProcessing] = useState(false);
    const alertsEnabled = useRemoteAlertsEnabled(auth.user?.id ?? null);
    const soundEnabled = useRemoteAlertsEnabled(auth.user?.id ?? null, 'sound');
    const supported = useRemoteNotificationSupport();
    const offsiteEnabled = useRemoteAlertsEnabled(
        auth.user?.id ?? null,
        'offsite',
    );
    const [savingAlerts, setSavingAlerts] = useState(false);

    const saveAlerts = (
        enabled: boolean,
        channel: RemoteAlertChannel = 'notifications',
    ) => {
        if (auth.user === null || savingAlerts) return;
        setSavingAlerts(true);
        void setRemoteAlerts(auth.user.id, enabled, channel)
            .then((saved) => {
                if (!saved)
                    toast.error(
                        channel === 'sound'
                            ? 'Could not save notification sound. Check your browser’s storage settings.'
                            : 'Could not save notifications. Check your browser’s site permissions and storage settings.',
                    );
            })
            .catch((error: unknown) =>
                toast.error(
                    error instanceof RemotePushValidationError
                        ? error.message
                        : enabled
                          ? 'Could not enable off-site notifications. Check this browser’s permissions and try again.'
                          : 'Could not turn off-site notifications off. Please try again.',
                ),
            )
            .finally(() => setSavingAlerts(false));
    };

    const toggle = () => {
        if (processing) {
            return;
        }

        router.put(
            update.url(),
            { enabled: !enabled },
            {
                preserveScroll: true,
                only: ['auth'],
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: () =>
                    toast.error('Could not save remotes. Please try again.'),
                onSuccess: () => router.flushAll(),
            },
        );
    };

    return (
        <section className="px-4 py-6 sm:px-6">
            <div className="flex items-start justify-between gap-6">
                <div className="min-w-0 space-y-2">
                    <h2 id="remotes-label" className="text-base font-semibold">
                        Remotes
                    </h2>
                    <p
                        id="remotes-description"
                        className="text-sm text-muted-foreground"
                    >
                        One-to-one messages with other developers. People you
                        follow can push to you, and you can push to people who
                        follow you. Whether you share pulls (read receipts) is
                        up to you, from Remotes.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        When off, nobody can message you and Remotes is hidden.
                        Your conversations are kept for when you turn it back
                        on.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={enabled}
                    aria-labelledby="remotes-label"
                    aria-describedby="remotes-description"
                    aria-disabled={processing}
                    onClick={toggle}
                    className={cn(
                        'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        enabled ? 'bg-primary' : 'bg-muted-foreground/30',
                    )}
                    data-test="remotes-setting"
                >
                    <span
                        className={cn(
                            'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                            enabled && 'translate-x-5',
                        )}
                    />
                </button>
            </div>
            <div className="mt-6 flex items-start justify-between gap-6 border-t pt-6">
                <div className="min-w-0 space-y-2">
                    <h3
                        id="remote-alerts-label"
                        className="text-sm font-medium"
                    >
                        On-site notifications
                    </h3>
                    <p
                        id="remote-alerts-description"
                        className="text-sm text-muted-foreground"
                    >
                        Show message alerts inside /dev/null.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={alertsEnabled}
                    aria-labelledby="remote-alerts-label"
                    aria-describedby="remote-alerts-description"
                    disabled={!enabled || savingAlerts}
                    onClick={() => saveAlerts(!alertsEnabled)}
                    className={cn(
                        'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50',
                        alertsEnabled ? 'bg-primary' : 'bg-muted-foreground/30',
                    )}
                    data-test="remote-alerts-setting"
                >
                    <span
                        className={cn(
                            'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                            alertsEnabled && 'translate-x-5',
                        )}
                    />
                </button>
            </div>
            <div className="mt-6 flex items-start justify-between gap-6 border-t pt-6">
                <div className="min-w-0 space-y-2">
                    <h3
                        id="remote-offsite-label"
                        className="text-sm font-medium"
                    >
                        Off-site notifications
                    </h3>
                    <p
                        id="remote-offsite-description"
                        className="text-sm text-muted-foreground"
                    >
                        Send notifications to this browser or installed app,
                        including when closed. No message previews.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        On iPhone and iPad, add /dev/null to your Home Screen
                        and turn this on from the installed app.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={offsiteEnabled}
                    aria-labelledby="remote-offsite-label"
                    aria-describedby="remote-offsite-description"
                    disabled={!enabled || !supported || savingAlerts}
                    onClick={() => saveAlerts(!offsiteEnabled, 'offsite')}
                    className={cn(
                        'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50',
                        offsiteEnabled
                            ? 'bg-primary'
                            : 'bg-muted-foreground/30',
                    )}
                    data-test="remote-offsite-setting"
                >
                    <span
                        className={cn(
                            'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                            offsiteEnabled && 'translate-x-5',
                        )}
                    />
                </button>
            </div>
            <div className="mt-6 flex items-start justify-between gap-6 border-t pt-6">
                <div className="min-w-0 space-y-2">
                    <h3 id="remote-sound-label" className="text-sm font-medium">
                        Notification sound
                    </h3>
                    <p
                        id="remote-sound-description"
                        className="text-sm text-muted-foreground"
                    >
                        Play a sound for new messages while the site is open.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={soundEnabled}
                    aria-labelledby="remote-sound-label"
                    aria-describedby="remote-sound-description"
                    disabled={!enabled || savingAlerts}
                    onClick={() => saveAlerts(!soundEnabled, 'sound')}
                    className={cn(
                        'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50',
                        soundEnabled ? 'bg-primary' : 'bg-muted-foreground/30',
                    )}
                    data-test="remote-sound-setting"
                >
                    <span
                        className={cn(
                            'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                            soundEnabled && 'translate-x-5',
                        )}
                    />
                </button>
            </div>
        </section>
    );
}
