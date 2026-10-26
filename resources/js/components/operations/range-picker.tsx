import type { ReactNode } from 'react';
import { PresetButton } from '@/components/operations/preset-button';
import type { TrafficRange } from '@/types';

export function utcDate(daysAgo = 0): string {
    return new Date(Date.now() - daysAgo * 86_400_000)
        .toISOString()
        .slice(0, 10);
}

/**
 * Presets of the last few days, ending today (UTC), and a from → to pair of date inputs. A preset is active when the
 * range is exactly its days, unless `isPresetRange` says otherwise. Anything else, like an "all time" button, comes
 * first as children.
 */
export function RangePicker({
    range,
    presets,
    onSelect,
    isPresetRange = true,
    children,
}: {
    range: TrafficRange;
    presets: { label: string; days: number }[];
    onSelect: (range: TrafficRange) => void;
    isPresetRange?: boolean;
    children?: ReactNode;
}) {
    const today = utcDate();

    return (
        <div className="flex flex-wrap items-center gap-2 font-mono text-[13px]">
            {children}
            {presets.map((preset) => {
                const from = utcDate(preset.days - 1);

                return (
                    <PresetButton
                        key={preset.label}
                        isActive={
                            isPresetRange &&
                            range.from === from &&
                            range.to === today
                        }
                        onClick={() => onSelect({ from, to: today })}
                    >
                        {preset.label}
                    </PresetButton>
                );
            })}
            <span className="flex w-full items-center gap-1.5 sm:ml-auto sm:w-auto">
                <input
                    type="date"
                    aria-label="From"
                    value={range.from}
                    max={range.to}
                    onChange={(event) =>
                        event.target.value &&
                        onSelect({ ...range, from: event.target.value })
                    }
                    className="h-9 min-w-0 flex-1 rounded-md border bg-transparent px-2 text-base tabular-nums sm:h-7 sm:flex-none sm:text-[13px]"
                />
                <span className="text-muted-foreground">→</span>
                <input
                    type="date"
                    aria-label="To"
                    value={range.to}
                    min={range.from}
                    max={today}
                    onChange={(event) =>
                        event.target.value &&
                        onSelect({ ...range, to: event.target.value })
                    }
                    className="h-9 min-w-0 flex-1 rounded-md border bg-transparent px-2 text-base tabular-nums sm:h-7 sm:flex-none sm:text-[13px]"
                />
            </span>
        </div>
    );
}
