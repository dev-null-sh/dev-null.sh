import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import UserEmbedsController from '@/actions/App/Http/Controllers/UserEmbedsController';
import { usePicked } from '@/hooks/use-picked';
import { cn } from '@/lib/utils';

/**
 * The signed-in user's own setting, in Settings → Profile.
 */
export function UserEmbedsSetting() {
    const { auth } = usePage().props;

    return (
        <EmbedsSetting
            enabled={auth.allowsEmbeds}
            url={UserEmbedsController.update.url()}
            owner="your"
        />
    );
}

/**
 * Whether others may embed a profile's commits on their sites: the user's in their settings, an organization's in its own.
 */
export function EmbedsSetting({
    enabled: saved,
    url,
    owner,
}: {
    enabled: boolean;
    url: string;
    /** Who the commits belong to, as the description names them: "your" or "@laravel's". */
    owner: string;
}) {
    const { picked, pick } = usePicked<boolean>();
    const processing = picked !== null;
    const enabled = picked ?? saved;

    const toggle = () => {
        if (processing) {
            return;
        }

        router.put(
            url,
            { enabled: !enabled },
            {
                preserveScroll: true,
                onFinish: pick(!enabled),
                onError: () =>
                    toast.error(
                        'Could not save the embeds preference. Please try again.',
                    ),
            },
        );
    };

    return (
        <section className="px-4 py-6 sm:px-6">
            <div className="flex items-start justify-between gap-6">
                <div className="min-w-0 space-y-2">
                    <h2 id="embeds-label" className="text-base font-semibold">
                        Embeds
                    </h2>
                    <p
                        id="embeds-description"
                        className="text-sm text-muted-foreground"
                    >
                        Let developers show {owner} commits on their sites, as a
                        card that stays in sync with /dev/null.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        When off, nobody can create new embeds, and the cards
                        already out there read{' '}
                        <span className="font-mono text-[13px]">
                            [unavailable]
                        </span>
                        .
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={enabled}
                    aria-labelledby="embeds-label"
                    aria-describedby="embeds-description"
                    aria-disabled={processing}
                    onClick={toggle}
                    className={cn(
                        'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        enabled ? 'bg-primary' : 'bg-muted-foreground/30',
                    )}
                    data-test="embeds-switch"
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
