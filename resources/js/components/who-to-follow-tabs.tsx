import { Eye, EyeOff } from 'pixelarticons/react';
import { useState } from 'react';
import { SegmentedControl } from '@/components/segmented-control';
import { useWhoToFollow } from '@/hooks/use-who-to-follow';
import { withViewTransition } from '@/lib/utils';
import type { IconComponent } from '@/types';

export default function WhoToFollowTabs() {
    const { showsWhoToFollow, setShowsWhoToFollow } = useWhoToFollow();
    const [selected, setSelected] = useState(showsWhoToFollow);

    const tabs: { value: boolean; icon: IconComponent; label: string }[] = [
        { value: true, icon: Eye, label: 'Show' },
        { value: false, icon: EyeOff, label: 'Hide' },
    ];

    const select = (value: boolean): void => {
        if (value === selected) {
            return;
        }

        void withViewTransition(() => setSelected(value)).then(() =>
            setShowsWhoToFollow(value),
        );
    };

    return (
        <SegmentedControl
            label="Who to follow"
            name="who-to-follow"
            options={tabs}
            value={selected}
            onChange={select}
        />
    );
}
