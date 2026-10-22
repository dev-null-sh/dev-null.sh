import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Palette, Sun } from 'lucide-react';
import { SegmentedControl } from '@/components/segmented-control';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { withViewTransition } from '@/lib/utils';

export default function AppearanceToggleTab({
    className,
}: {
    className?: string;
}) {
    const { appearance, updateAppearance } = useAppearance();
    const { omarchy } = usePage().props;

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
        ...(omarchy
            ? [{ value: 'omarchy' as const, icon: Palette, label: 'Omarchy' }]
            : []),
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
            className={className}
        />
    );
}
