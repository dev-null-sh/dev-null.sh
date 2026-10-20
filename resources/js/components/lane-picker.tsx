import { Form, router } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useRef, useState } from 'react';
import UserLaneController from '@/actions/App/Http/Controllers/UserLaneController';
import { GraphRail, RailAvatar } from '@/components/graph-rail';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useUser } from '@/hooks/use-user';
import { cn, laneColor, withViewTransition } from '@/lib/utils';
import type { Lane } from '@/types';

const laneNames = ['Orange', 'Green', 'Violet', 'Teal', 'Rose', 'Ochre'];

const optionClassName =
    'flex cursor-pointer flex-col items-center gap-1.5 rounded-md py-2 transition-colors hover:bg-accent has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50';

const optionLabelClassName =
    'text-xs text-muted-foreground peer-checked:font-medium peer-checked:text-foreground';

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
    selected: Lane | null;
    automaticLane: number;
    onSelect: (lane: Lane | null) => void;
}) {
    const colorInputRef = useRef<HTMLInputElement>(null);
    const [customColor, setCustomColor] = useState(
        typeof selected === 'string' ? selected : null,
    );
    const isCustom = typeof selected === 'string';

    const options = [
        {
            value: null,
            label: 'Auto',
            ariaLabel: `Automatic (${laneNames[automaticLane].toLowerCase()})`,
            lane: automaticLane,
        },
        ...laneNames.map((name, index) => ({
            value: index,
            label: name,
            ariaLabel: undefined,
            lane: index,
        })),
    ];

    const openColorPicker = (radio: HTMLInputElement) => {
        const colorInput = colorInputRef.current;

        if (colorInput === null) {
            return;
        }

        if (customColor === null) {
            colorInput.value = getComputedStyle(radio)
                .getPropertyValue('--lane')
                .trim();
        }

        // iOS color pickers open on focus; other browsers need click activation.
        colorInput.focus({ preventScroll: true });
        colorInput.click();
    };

    const chooseCustomColor = (color: string) => {
        setCustomColor(color);
        onSelect(color);
    };

    return (
        <fieldset>
            <legend className="sr-only">Color</legend>
            <div className="grid max-w-md grid-cols-4 gap-1 sm:grid-cols-8">
                {options.map((option) => (
                    <label
                        key={option.label}
                        className={optionClassName}
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
                        <span className={optionLabelClassName}>
                            {option.label}
                        </span>
                    </label>
                ))}
                <label
                    className={cn(optionClassName, 'relative')}
                    style={
                        {
                            '--lane':
                                customColor ??
                                laneColor(
                                    typeof selected === 'number'
                                        ? selected
                                        : automaticLane,
                                ),
                        } as CSSProperties
                    }
                    data-test="custom-lane-option"
                >
                    <input
                        type="radio"
                        name="lane"
                        value=""
                        checked={isCustom}
                        onChange={() => {
                            if (customColor !== null) {
                                onSelect(customColor);
                            }
                        }}
                        onClick={(event) =>
                            openColorPicker(event.currentTarget)
                        }
                        aria-label={
                            customColor === null
                                ? 'Custom color'
                                : `Custom color (${customColor})`
                        }
                        className="peer sr-only"
                    />
                    <input
                        ref={colorInputRef}
                        type="color"
                        name={isCustom ? 'color' : undefined}
                        defaultValue={customColor ?? undefined}
                        onChange={(event) =>
                            chooseCustomColor(event.target.value)
                        }
                        tabIndex={-1}
                        aria-hidden
                        className="pointer-events-none absolute bottom-0 left-1/2 size-px opacity-0"
                        data-test="custom-lane-color"
                    />
                    <span
                        className={cn(
                            'relative flex size-7 items-center justify-center rounded-full',
                            customColor === null
                                ? 'bg-[conic-gradient(#f03e3e,#fd7e14,#fcc419,#51cf66,#22b8cf,#4c6ef5,#ae3ec9,#f03e3e)]'
                                : 'bg-(--lane)',
                        )}
                    >
                        {isCustom && (
                            <span
                                aria-hidden
                                className="absolute -inset-1 rounded-full border-2 border-(--lane) in-place:[view-transition-name:lane-selected]"
                            />
                        )}
                    </span>
                    <span className={optionLabelClassName}>Custom</span>
                </label>
            </div>
        </fieldset>
    );
}

export default function LanePicker({
    preferredLane,
    automaticLane,
}: {
    preferredLane: Lane | null;
    automaticLane: number;
}) {
    const [selected, setSelected] = useState<Lane | null>(preferredLane);

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Color"
                description="Your lane on the graph: the rail and nodes beside your commits, the ring around your avatar, and the dot before your handle. Automatic picks one from your account id."
            />

            <LanePreview lane={laneColor(selected ?? automaticLane)} />

            <Form
                {...UserLaneController.update.form()}
                options={{ preserveScroll: true }}
                onSuccess={() => router.flushAll()}
                className="mt-5"
            >
                {({ processing, errors }) => (
                    <>
                        <LaneOptions
                            selected={selected}
                            automaticLane={automaticLane}
                            onSelect={(lane) => {
                                if (
                                    typeof lane === 'string' &&
                                    typeof selected === 'string'
                                ) {
                                    setSelected(lane);

                                    return;
                                }

                                void withViewTransition(() =>
                                    setSelected(lane),
                                );
                            }}
                        />

                        <InputError
                            message={errors.lane ?? errors.color}
                            className="mt-2"
                        />

                        <Button
                            type="submit"
                            className="mt-5"
                            disabled={processing || selected === preferredLane}
                            data-test="save-lane-button"
                        >
                            Save color
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
