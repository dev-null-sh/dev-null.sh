import { Head, router } from '@inertiajs/react';
import { useId, useState } from 'react';
import { toast } from 'sonner';
import FeatureController from '@/actions/App/Http/Controllers/FeatureController';
import { Section } from '@/components/operations/section';
import AppLayout from '@/layouts/app-layout';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type { Feature } from '@/types';

const FEATURES: Record<
    Feature['name'],
    { label: string; description: string }
> = {
    translations: {
        label: 'translations',
        description:
            'The Translate control, cached translations, and background language detection. Off stops new detection jobs from being queued and skips any already waiting.',
    },
    'algolia-user-search': {
        label: 'algolia user search',
        description:
            'Developer search and the remote picker search Algolia once its search key is set. Off searches the database instead; the index keeps syncing either way.',
    },
};

function FeatureRow({ feature }: { feature: Feature }) {
    const [processing, setProcessing] = useState(false);
    const labelId = useId();
    const descriptionId = useId();
    const { label, description } = FEATURES[feature.name];

    const toggle = () => {
        if (processing) {
            return;
        }

        const enabled = !feature.enabled;

        router.put(
            FeatureController.update.url(feature.name),
            { enabled },
            {
                only: ['features'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () =>
                    toast(
                        <span className="font-mono text-[13px]">
                            {enabled
                                ? `Switched ${label} on for everyone`
                                : `Switched ${label} off for everyone`}
                        </span>,
                    ),
                onError: () =>
                    toast.error(
                        <span className="font-mono text-[13px]">
                            fatal: could not switch {label}{' '}
                            {enabled ? 'on' : 'off'}
                        </span>,
                    ),
            },
        );
    };

    return (
        <li
            className="flex items-start justify-between gap-6 px-4 py-3"
            data-test="feature"
        >
            <div className="min-w-0 space-y-1">
                <p id={labelId} className="font-mono text-[13px] font-medium">
                    {label}
                </p>
                <p id={descriptionId} className="text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <button
                type="button"
                role="switch"
                aria-checked={feature.enabled}
                aria-labelledby={labelId}
                aria-describedby={descriptionId}
                aria-disabled={processing}
                onClick={toggle}
                className={cn(
                    'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    feature.enabled ? 'bg-primary' : 'bg-muted-foreground/30',
                )}
                data-test={`feature-${feature.name}-switch`}
            >
                <span
                    className={cn(
                        'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                        feature.enabled && 'translate-x-5',
                    )}
                />
            </button>
        </li>
    );
}

export default function Index({ features }: { features: Feature[] }) {
    return (
        <>
            <Head title="Feature switches" />

            <Section
                title="Features"
                meta={`${formatCount(features.filter((feature) => !feature.enabled).length)} off`}
            >
                <ul className="divide-y rounded-lg border">
                    {features.map((feature) => (
                        <FeatureRow key={feature.name} feature={feature} />
                    ))}
                </ul>
            </Section>
        </>
    );
}

Index.layout = [AppLayout, OperationsLayout];
