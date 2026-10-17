import { useLang } from '@erag/lang-sync-inertia/react';
import type { LucideIcon } from 'lucide-react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { SegmentedControl } from '@/components/segmented-control';
import { useWhoToFollow } from '@/hooks/use-who-to-follow';
import { withViewTransition } from '@/lib/utils';

export default function WhoToFollowTabs() {
    const { showsWhoToFollow, setShowsWhoToFollow } = useWhoToFollow();
    const [selected, setSelected] = useState(showsWhoToFollow);
    const { __ } = useLang();

    const tabs: { value: boolean; icon: LucideIcon; label: string }[] = [
        { value: true, icon: Eye, label: __('settings.appearance.show') },
        { value: false, icon: EyeOff, label: __('settings.appearance.hide') },
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
            label={__('settings.appearance.who_to_follow')}
            name="who-to-follow"
            options={tabs}
            value={selected}
            onChange={select}
        />
    );
}
