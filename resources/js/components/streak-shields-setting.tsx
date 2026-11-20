import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserStreakShieldsController';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';

export function StreakShieldsControl({
    enabled,
    saving,
    onToggle,
}: {
    enabled: boolean;
    saving: boolean;
    onToggle: () => void;
}) {
    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Streak Shields"
                description="Earn one shield every 7 commit days in a streak, up to 2. Protected days preserve your streak without increasing its length."
            />
            <div className="flex items-start justify-between gap-6">
                <div className="min-w-0 space-y-2">
                    <h3
                        id="streak-shields-label"
                        className="text-sm font-medium"
                    >
                        Automatically protect my streak
                    </h3>
                    <p
                        id="streak-shields-description"
                        className="text-sm text-muted-foreground"
                    >
                        Use a shield when you miss a completed day. Turn off to
                        save shields; missed days can then end your streak.
                        Changes apply from today and keep earlier protection
                        intact.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={enabled}
                    aria-labelledby="streak-shields-label"
                    aria-describedby="streak-shields-description"
                    disabled={saving}
                    aria-busy={saving}
                    onClick={onToggle}
                    className={cn(
                        'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50',
                        enabled ? 'bg-primary' : 'bg-muted-foreground/30',
                    )}
                >
                    <span
                        aria-hidden
                        className={cn(
                            'size-4 rounded-full bg-background shadow-sm transition-transform',
                            enabled ? 'translate-x-5' : 'translate-x-0',
                        )}
                    />
                </button>
            </div>
            <p className="mt-3 text-sm text-muted-foreground">
                You’ll receive in-app notifications when shields are earned or
                used, including when your last shield is spent, and when a
                streak ends. Notifications are checked hourly.
            </p>
            <p
                role="status"
                className="mt-2 min-h-5 text-sm text-muted-foreground"
            >
                {saving
                    ? 'Saving protection preference…'
                    : enabled
                      ? 'Automatic protection is on.'
                      : 'Automatic protection is off.'}
            </p>
        </section>
    );
}

export function StreakShieldsSetting() {
    const { auth } = usePage().props;
    const [saving, setSaving] = useState(false);
    const enabled = auth.automaticStreakShields;

    return (
        <StreakShieldsControl
            enabled={enabled}
            saving={saving}
            onToggle={() => {
                if (saving) return;
                setSaving(true);
                router.put(
                    update.url(),
                    { enabled: !enabled },
                    {
                        preserveScroll: true,
                        only: ['auth'],
                        onError: () =>
                            toast.error(
                                'Could not save streak protection. Please try again.',
                            ),
                        onSuccess: () => router.flushAll(),
                        onFinish: () => setSaving(false),
                    },
                );
            }}
        />
    );
}
