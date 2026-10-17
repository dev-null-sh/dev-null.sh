import { useLang } from '@erag/lang-sync-inertia/react';
import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
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
    const { __ } = useLang();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: __('settings.appearance.light') },
        { value: 'dark', icon: Moon, label: __('settings.appearance.dark') },
        {
            value: 'system',
            icon: Monitor,
            label: __('settings.appearance.system'),
        },
    ];

    return (
        <SegmentedControl
            label={__('settings.appearance.theme')}
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
