import { router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ToggleLeft, ToggleRight } from 'lucide-react';
import { useState } from 'react';
import { SegmentedControl } from '@/components/segmented-control';
import { setCookie } from '@/hooks/use-appearance';
import { withViewTransition } from '@/lib/utils';

export default function LargeEmojiTabs() {
    const { showsLargeEmoji } = usePage().props;
    const [selected, setSelected] = useState(showsLargeEmoji);

    const tabs: { value: boolean; icon: LucideIcon; label: string }[] = [
        { value: true, icon: ToggleRight, label: 'On' },
        { value: false, icon: ToggleLeft, label: 'Off' },
    ];

    const select = (value: boolean): void => {
        if (value === selected) {
            return;
        }

        void withViewTransition(() => setSelected(value)).then(() => {
            setCookie('large_emoji', String(value));
            router.flushAll();
            router.reload({ only: ['showsLargeEmoji'] });
        });
    };

    return (
        <SegmentedControl
            label="Large emoji"
            name="large-emoji"
            options={tabs}
            value={selected}
            onChange={select}
        />
    );
}
