import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserFederationController';
import { cn } from '@/lib/utils';

export function FederationSetting({
    enabled,
    handle,
}: {
    enabled: boolean;
    handle: string;
}) {
    const [processing, setProcessing] = useState(false);

    const toggle = () => {
        if (processing) {
            return;
        }

        router.put(
            update.url(),
            { enabled: !enabled },
            {
                preserveScroll: true,
                only: ['federates'],
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: () =>
                    toast.error(
                        'Could not save the Mastodon setting. Please try again.',
                    ),
            },
        );
    };

    return (
        <section className="px-4 py-6 sm:px-6">
            <div className="flex items-start justify-between gap-6">
                <div className="min-w-0 space-y-2">
                    <h2
                        id="federation-label"
                        className="text-base font-semibold"
                    >
                        Follow from Mastodon
                    </h2>
                    <p
                        id="federation-description"
                        className="text-sm text-muted-foreground"
                    >
                        People on Mastodon can follow you as{' '}
                        <span className="font-mono text-foreground">
                            {handle}
                        </span>{' '}
                        and read your commits there. Only approved commits are
                        sent, never replies.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Commits that reach Mastodon stay on those servers, even
                        if you turn this off later.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={enabled}
                    aria-labelledby="federation-label"
                    aria-describedby="federation-description"
                    aria-disabled={processing}
                    onClick={toggle}
                    className={cn(
                        'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        enabled ? 'bg-primary' : 'bg-muted-foreground/30',
                    )}
                >
                    <span
                        className={cn(
                            'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                            enabled && 'translate-x-5',
                        )}
                    />
                </button>
            </div>
        </section>
    );
}
