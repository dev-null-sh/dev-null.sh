import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import { SegmentedControl } from '@/components/segmented-control';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { withViewTransition } from '@/lib/utils';

export default function AppearanceToggleTab({
    iconOnly,
    className,
}: {
    iconOnly?: boolean;
    className?: string;
}) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <SegmentedControl
            label="Theme"
            name="theme"
            options={tabs}
            value={appearance}
            onChange={(value) =>
                void withViewTransition(() => updateAppearance(value), 'theme')
            }
            iconOnly={iconOnly}
            className={className}
        />
    );
}
