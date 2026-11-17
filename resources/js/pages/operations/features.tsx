import { Head, router } from '@inertiajs/react';
import { useId } from 'react';
import { toast } from 'sonner';
import FeatureController from '@/actions/App/Http/Controllers/FeatureController';
import { Panel } from '@/components/operations/section';
import { SegmentedControl } from '@/components/segmented-control';
import { usePicked } from '@/hooks/use-picked';
import OperationsLayout from '@/layouts/operations/layout';
import { formatCount } from '@/lib/numbers';
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
    guilds: {
        label: 'guilds',
        description:
            'Public guild pages, creation, editing, and inline hashtag chips. Choose who can use the preview.',
    },
};

function FeatureRow({ feature }: { feature: Feature }) {
    const { picked, pick } = usePicked<'off' | 'admins' | 'everyone'>();
    const labelId = useId();
    const descriptionId = useId();
    const { label, description } = FEATURES[feature.name];
    const processing = picked !== null;
    const audience =
        picked ??
        (!feature.enabled ? 'off' : feature.admin_only ? 'admins' : 'everyone');

    const change = (next: 'off' | 'admins' | 'everyone') => {
        if (processing || next === audience) {
            return;
        }

        router.put(
            FeatureController.update.url(feature.name),
            { enabled: next !== 'off', admin_only: next === 'admins' },
            {
                only: ['features'],
                preserveState: true,
                preserveScroll: true,
                onFinish: pick(next),
                onSuccess: () =>
                    toast(
                        <span className="font-mono text-[13px]">
                            {label}: {next}
                        </span>,
                    ),
                onError: () =>
                    toast.error(
                        <span className="font-mono text-[13px]">
                            fatal: could not update {label}
                        </span>,
                    ),
            },
        );
    };

    return (
        <li
            className="flex flex-wrap items-start justify-between gap-3 px-4 py-3"
            data-test="feature"
        >
            <div className="min-w-0 flex-1 basis-64 space-y-1">
                <p id={labelId} className="font-mono text-[13px] font-medium">
                    {label}
                </p>
                <p id={descriptionId} className="text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <fieldset
                disabled={processing}
                aria-describedby={descriptionId}
                className="shrink-0"
            >
                <SegmentedControl
                    label={`${label} availability`}
                    name={`feature-${feature.name}`}
                    options={[
                        { value: 'off', label: 'Off' },
                        { value: 'admins', label: 'Admins' },
                        { value: 'everyone', label: 'Everyone' },
                    ]}
                    value={audience}
                    onChange={change}
                />
            </fieldset>
        </li>
    );
}

export default function Index({ features }: { features: Feature[] }) {
    return (
        <>
            <Head title="Features" />

            <Panel
                className="max-w-3xl"
                title="Features"
                meta={`${formatCount(features.filter((feature) => !feature.enabled).length)} off`}
            >
                <ul className="divide-y rounded-lg border">
                    {features.map((feature) => (
                        <FeatureRow key={feature.name} feature={feature} />
                    ))}
                </ul>
            </Panel>
        </>
    );
}

Index.layout = OperationsLayout;
