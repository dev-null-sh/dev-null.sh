import { router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ToggleLeft, ToggleRight } from 'lucide-react';
import { useState } from 'react';
import { SegmentedControl } from '@/components/segmented-control';
import { setCookie } from '@/hooks/use-appearance';
import { withViewTransition } from '@/lib/utils';

export default function LaneDimmingTabs() {
    const { dimsLanes } = usePage().props;
    const [selected, setSelected] = useState(dimsLanes);

    const tabs: { value: boolean; icon: LucideIcon; label: string }[] = [
        { value: true, icon: ToggleRight, label: 'On' },
        { value: false, icon: ToggleLeft, label: 'Off' },
    ];

    const select = (value: boolean): void => {
        if (value === selected) {
            return;
        }

        void withViewTransition(() => setSelected(value)).then(() => {
            setCookie('lane_dimming', String(value));
            router.flushAll();
            router.reload({ only: ['dimsLanes'] });
        });
    };

    return (
        <SegmentedControl
            label="Lane dimming"
            name="lane-dimming"
            options={tabs}
            value={selected}
            onChange={select}
        />
    );
}
