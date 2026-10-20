import type { LucideIcon } from 'lucide-react';
import { PanelLeft, PanelLeftClose } from 'lucide-react';
import { useState } from 'react';
import { SegmentedControl } from '@/components/segmented-control';
import { useNavOpen } from '@/hooks/use-nav-open';
import { withViewTransition } from '@/lib/utils';

export default function NavigationTabs() {
    const { isNavOpen, setNavOpen } = useNavOpen();
    const [selected, setSelected] = useState(isNavOpen);
    const [saved, setSaved] = useState(isNavOpen);

    if (saved !== isNavOpen) {
        setSaved(isNavOpen);
        setSelected(isNavOpen);
    }

    const tabs: { value: boolean; icon: LucideIcon; label: string }[] = [
        { value: true, icon: PanelLeft, label: 'Expanded' },
        { value: false, icon: PanelLeftClose, label: 'Collapsed' },
    ];

    const select = (value: boolean): void => {
        if (value === selected) {
            return;
        }

        void withViewTransition(() => setSelected(value)).then(() =>
            setNavOpen(value),
        );
    };

    return (
        <SegmentedControl
            label="Navigation"
            name="navigation"
            options={tabs}
            value={selected}
            onChange={select}
        />
    );
}
