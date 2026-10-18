import { Link, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import SponsorshipSyncController from '@/actions/App/Http/Controllers/SponsorshipSyncController';
import SponsorsOrgController from '@/actions/App/Http/Controllers/SponsorsOrgController';
import { Button } from '@/components/ui/button';
import {
    sponsorsFromChoices,
    sponsorsFromDescriptions,
    sponsorsFromLabels,
} from '@/lib/sponsors';
import { cn } from '@/lib/utils';
import { show } from '@/routes/org';
import type { SponsorsFrom } from '@/types';

export type SponsorsOrg = {
    id: string;
    from: SponsorsFrom;
    sponsors: number;
};

/**
 * An org of the user's GitHub sponsors that keeps itself up to date. Turning it on goes to GitHub for the sponsors;
 * turning it off deletes the org and its history, after a confirm in the button's own place.
 */
export function SponsorsOrgSetting({ org }: { org: SponsorsOrg | null }) {
    const [from, setFrom] = useState<SponsorsFrom>(org?.from ?? 'active');
    const [processing, setProcessing] = useState(false);
    const [isConfirmingOff, setIsConfirmingOff] = useState(false);

    const choose = (choice: SponsorsFrom) => {
        setFrom(choice);

        if (org === null || choice === org.from) {
            return;
        }

        router.put(
            SponsorsOrgController.update.url(),
            { from: choice },
            {
                preserveScroll: true,
                only: ['sponsorsOrg'],
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: () => {
                    setFrom(org.from);
                    toast.error('Could not save. Please try again.');
                },
            },
        );
    };

    const turnOn = () => {
        router.post(
            SponsorsOrgController.store.url(),
            { from },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: () =>
                    toast.error('Could not start the org. Please try again.'),
            },
        );
    };

    const turnOff = () => {
        router.delete(SponsorsOrgController.destroy.url(), {
            preserveScroll: true,
            only: ['sponsorsOrg'],
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setIsConfirmingOff(false);
            },
            onError: () => toast.error('Could not turn it off. Try again.'),
            onSuccess: () => router.flushAll(),
        });
    };

    return (
        <section className="px-4 py-6 sm:px-6">
            <div className="min-w-0 space-y-2">
                <h2 id="sponsors-org-label" className="text-base font-semibold">
                    Sponsors org
                </h2>
                <p className="text-sm text-muted-foreground">
                    An org of your GitHub sponsors that keeps itself up to date.
                    Sponsors are let in and out each time you or they sign in
                    with GitHub. They don't see who else is in it unless they
                    show their membership. You always see everyone.
                </p>
            </div>

            <fieldset
                aria-labelledby="sponsors-org-label"
                className="mt-4 grid gap-2 sm:grid-cols-3"
                disabled={processing}
            >
                {sponsorsFromChoices.map((choice) => (
                    <label
                        key={choice}
                        className={cn(
                            'flex cursor-pointer flex-col gap-1 rounded-lg border bg-card px-3 py-2.5 transition-colors has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                            from === choice
                                ? 'border-primary'
                                : 'hover:bg-accent',
                        )}
                    >
                        <input
                            type="radio"
                            name="sponsors-from"
                            value={choice}
                            checked={from === choice}
                            onChange={() => choose(choice)}
                            className="sr-only"
                            data-test={`sponsors-from-${choice}`}
                        />
                        <span className="text-sm font-medium">
                            {sponsorsFromLabels[choice]}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {sponsorsFromDescriptions[choice]}
                        </span>
                    </label>
                ))}
            </fieldset>

            <div className="mt-4 flex flex-wrap items-center gap-2">
                {org === null ? (
                    <Button
                        onClick={turnOn}
                        disabled={processing}
                        className="min-w-36"
                        data-test="sponsors-org-on"
                    >
                        Turn on
                    </Button>
                ) : (
                    <>
                        <Button variant="outline" asChild>
                            <Link href={show(org.id)}>
                                Open org
                                <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                    {org.sponsors}
                                </span>
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={SponsorshipSyncController.create.url()}>
                                <RefreshCw className="size-4" />
                                Sync from GitHub
                            </a>
                        </Button>
                        <Button
                            variant={isConfirmingOff ? 'destructive' : 'ghost'}
                            className="min-w-44 sm:ml-auto"
                            disabled={processing}
                            onClick={() =>
                                isConfirmingOff
                                    ? turnOff()
                                    : setIsConfirmingOff(true)
                            }
                            onBlur={() => setIsConfirmingOff(false)}
                            data-test="sponsors-org-off"
                        >
                            {isConfirmingOff
                                ? 'Delete org and history'
                                : 'Turn off'}
                        </Button>
                    </>
                )}
            </div>
        </section>
    );
}
