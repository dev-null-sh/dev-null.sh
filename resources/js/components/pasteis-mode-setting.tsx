import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserPasteisModeController';
import { usePicked } from '@/hooks/use-picked';
import { cn } from '@/lib/utils';

export function PasteisModeSetting() {
    const { auth } = usePage().props;
    const { picked, pick } = usePicked<boolean>();
    const processing = picked !== null;
    const enabled = picked ?? auth.pasteis !== null;

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
                onFinish: pick(!enabled),
                onError: () =>
                    toast.error(
                        'Could not save pastéis mode. Please try again.',
                    ),
                onSuccess: () => router.flushAll(),
            },
        );
    };

    return (
        <section className="px-4 py-6 sm:px-6">
            <div className="flex items-start justify-between gap-6">
                <div className="min-w-0 space-y-2">
                    <h2
                        id="pasteis-mode-label"
                        className="text-base font-semibold"
                    >
                        Pastéis mode
                    </h2>
                    <p
                        id="pasteis-mode-description"
                        className="text-sm text-muted-foreground"
                    >
                        A little appreciation, fresh daily. Get 10 pastéis to
                        give to other people’s commits, one per click. Your
                        allowance resets at 00:00 UTC.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        When off, all pastéis controls and counters are hidden.
                        You still receive pastéis from others.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={enabled}
                    aria-labelledby="pasteis-mode-label"
                    aria-describedby="pasteis-mode-description"
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
