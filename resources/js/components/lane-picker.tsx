import { useLang } from '@erag/lang-sync-inertia/react';
import { Form } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import UserLaneController from '@/actions/App/Http/Controllers/UserLaneController';
import { GraphRail, RailAvatar } from '@/components/graph-rail';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useUser } from '@/hooks/use-user';
import { cn, laneColor, withViewTransition } from '@/lib/utils';

export function LanePreview({ lane }: { lane: string }) {
    const { user } = useUser();

    return (
        <div
            aria-hidden
            className="flex overflow-hidden rounded-lg border"
            style={{ '--lane': lane } as CSSProperties}
            data-test="lane-preview"
        >
            <GraphRail
                lane={lane}
                previousLane={lane}
                node={<RailAvatar user={user} />}
            />
            <div className="flex min-w-0 items-center pr-4">
                <div className="min-w-0">
                    <p className="truncate font-semibold">{user.name}</p>
                    <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                        <span className="size-2 shrink-0 rounded-full bg-(--lane) transition-colors duration-200" />
                        <span className="truncate">@{user.username}</span>
                    </p>
                </div>
            </div>
        </div>
    );
}

export function LaneOptions({
    selected,
    automaticLane,
    onSelect,
}: {
    selected: number | null;
    automaticLane: number;
    onSelect: (lane: number | null) => void;
}) {
    const { __ } = useLang();
    const laneNames = [
        __('settings.lane.names.orange'),
        __('settings.lane.names.green'),
        __('settings.lane.names.violet'),
        __('settings.lane.names.teal'),
        __('settings.lane.names.rose'),
        __('settings.lane.names.ochre'),
    ];
    const options = [
        {
            value: null,
            label: __('settings.lane.auto'),
            ariaLabel: __('settings.lane.automatic', {
                color: laneNames[automaticLane].toLowerCase(),
            }),
            lane: automaticLane,
        },
        ...laneNames.map((name, index) => ({
            value: index,
            label: name,
            ariaLabel: undefined,
            lane: index,
        })),
    ];

    return (
        <fieldset>
            <legend className="sr-only">{__('settings.lane.title')}</legend>
            <div className="grid max-w-md grid-cols-7 gap-1">
                {options.map((option) => (
                    <label
                        key={option.label}
                        className="flex cursor-pointer flex-col items-center gap-1.5 rounded-md py-2 transition-colors hover:bg-accent has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                        style={
                            {
                                '--lane': laneColor(option.lane),
                            } as CSSProperties
                        }
                    >
                        <input
                            type="radio"
                            name="lane"
                            value={option.value ?? ''}
                            checked={selected === option.value}
                            onChange={() => onSelect(option.value)}
                            aria-label={option.ariaLabel}
                            className="peer sr-only"
                        />
                        <span
                            className={cn(
                                'relative flex size-7 items-center justify-center rounded-full',
                                option.value === null
                                    ? 'border-2 border-dashed border-(--lane)'
                                    : 'bg-(--lane)',
                            )}
                        >
                            {option.value === null && (
                                <span className="size-2.5 rounded-full bg-(--lane)" />
                            )}
                            {selected === option.value && (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'absolute rounded-full border-2 border-(--lane) in-place:[view-transition-name:lane-selected]',
                                        option.value === null
                                            ? '-inset-1.5'
                                            : '-inset-1',
                                    )}
                                />
                            )}
                        </span>
                        <span className="text-xs text-muted-foreground peer-checked:font-medium peer-checked:text-foreground">
                            {option.label}
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

export default function LanePicker({
    preferredLane,
    automaticLane,
}: {
    preferredLane: number | null;
    automaticLane: number;
}) {
    const [selected, setSelected] = useState<number | null>(preferredLane);
    const { __ } = useLang();

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title={__('settings.lane.title')}
                description={__('settings.lane.description')}
            />

            <LanePreview lane={laneColor(selected ?? automaticLane)} />

            <Form
                {...UserLaneController.update.form()}
                options={{ preserveScroll: true }}
                className="mt-5"
            >
                {({ processing, errors }) => (
                    <>
                        <LaneOptions
                            selected={selected}
                            automaticLane={automaticLane}
                            onSelect={(lane) =>
                                void withViewTransition(() => setSelected(lane))
                            }
                        />

                        <InputError message={errors.lane} className="mt-2" />

                        <Button
                            type="submit"
                            className="mt-5"
                            disabled={processing || selected === preferredLane}
                            data-test="save-lane-button"
                        >
                            {__('settings.lane.save')}
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
