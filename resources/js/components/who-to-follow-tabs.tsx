import type { LucideIcon } from 'lucide-react';
import { EyeOff } from 'lucide-react';
import { useState } from 'react';
import { SegmentedControl } from '@/components/segmented-control';
import { useWhoToFollow } from '@/hooks/use-who-to-follow';
import { withViewTransition } from '@/lib/utils';

const counts = [1, 3, 5];

/**
 * Hide stands for 0, so hiding keeps the count chosen before for when the suggestions come back.
 */
export default function WhoToFollowTabs() {
    const {
        showsWhoToFollow,
        setShowsWhoToFollow,
        whoToFollowCount,
        setWhoToFollowCount,
    } = useWhoToFollow();
    const [selected, setSelected] = useState(
        showsWhoToFollow ? whoToFollowCount : 0,
    );

    const tabs: { value: number; icon?: LucideIcon; label: string }[] = [
        { value: 0, icon: EyeOff, label: 'Hide' },
        ...counts.map((count) => ({ value: count, label: String(count) })),
    ];

    const select = (value: number): void => {
        if (value === selected) {
            return;
        }

        void withViewTransition(() => setSelected(value)).then(() =>
            value === 0
                ? setShowsWhoToFollow(false)
                : setWhoToFollowCount(value),
        );
    };

    return (
        <SegmentedControl
            label="Who to follow"
            name="who-to-follow"
            options={tabs}
            value={selected}
            onChange={select}
            className="tabular-nums"
        />
    );
}
