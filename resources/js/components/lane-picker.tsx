import { Form, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import UserLaneController from '@/actions/App/Http/Controllers/UserLaneController';
import { GraphRail } from '@/components/graph-rail';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { cn, laneColor } from '@/lib/utils';

const laneNames = ['Orange', 'Green', 'Violet', 'Teal', 'Rose', 'Ochre'];

export default function LanePicker({
    preferredLane,
    automaticLane,
}: {
    preferredLane: number | null;
    automaticLane: number;
}) {
    const { auth } = usePage().props;
    const [selected, setSelected] = useState<number | null>(preferredLane);
    const lane = laneColor(selected ?? automaticLane);

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

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Color"
                description="Your lane on the graph: the rail and nodes beside your commits, the ring around your avatar, and the dot before your handle. Automatic picks one from your account id."
            />

            <div
                aria-hidden
                className="flex overflow-hidden rounded-lg border"
                style={{ '--lane': lane } as CSSProperties}
                data-test="lane-preview"
            >
                <GraphRail lane={lane} previousLane={lane} />
                <div className="flex min-w-0 items-center gap-3 py-4 pr-4">
                    <UserAvatar
                        user={auth.user}
                        className="size-12 ring-2 ring-(--lane) ring-offset-2 ring-offset-card transition-shadow duration-200"
                    />
                    <div className="min-w-0">
                        <p className="truncate font-semibold">
                            {auth.user.name}
                        </p>
                        <p className="flex items-center gap-1.5 font-mono text-sm text-muted-foreground">
                            <span className="size-2 shrink-0 rounded-full bg-(--lane) transition-colors duration-200" />
                            <span className="truncate">
                                @{auth.user.username}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <Form
                {...UserLaneController.update.form()}
                options={{ preserveScroll: true }}
                className="mt-5"
            >
                {({ processing, errors }) => (
                    <>
                        <fieldset>
                            <legend className="sr-only">Color</legend>
                            <div className="grid max-w-md grid-cols-7 gap-1">
                                {options.map((option) => (
                                    <label
                                        key={option.label}
                                        className="flex cursor-pointer flex-col items-center gap-1.5 rounded-md py-2 transition-colors hover:bg-accent has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                                        style={
                                            {
                                                '--lane': laneColor(
                                                    option.lane,
                                                ),
                                            } as CSSProperties
                                        }
                                    >
                                        <input
                                            type="radio"
                                            name="lane"
                                            value={option.value ?? ''}
                                            checked={selected === option.value}
                                            onChange={() =>
                                                setSelected(option.value)
                                            }
                                            aria-label={option.ariaLabel}
                                            className="peer sr-only"
                                        />
                                        <span
                                            className={cn(
                                                'flex size-7 items-center justify-center rounded-full ring-offset-2 ring-offset-card peer-checked:ring-2 peer-checked:ring-(--lane)',
                                                option.value === null
                                                    ? 'border-2 border-dashed border-(--lane)'
                                                    : 'bg-(--lane)',
                                            )}
                                        >
                                            {option.value === null && (
                                                <span className="size-2.5 rounded-full bg-(--lane)" />
                                            )}
                                        </span>
                                        <span className="text-xs text-muted-foreground peer-checked:font-medium peer-checked:text-foreground">
                                            {option.label}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <InputError message={errors.lane} className="mt-2" />

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
