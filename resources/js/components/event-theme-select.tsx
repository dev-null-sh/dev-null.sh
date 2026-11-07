import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppearance } from '@/hooks/use-appearance';
import type { EventThemeChoice } from '@/lib/event-theme';
import { EVENT_THEME_CHOICES, setEventThemeChoice } from '@/lib/event-theme';
import { withViewTransition } from '@/lib/utils';

export default function EventThemeSelect() {
    const { eventThemeChoice } = usePage().props;
    const { appearance, updateAppearance } = useAppearance();
    const [selected, setSelected] = useState(eventThemeChoice);

    const select = (value: string): void => {
        if (value === selected) {
            return;
        }

        void withViewTransition(() => {
            setSelected(value);
            setEventThemeChoice(value as EventThemeChoice);
            // Re-applies the appearance, which refreshes the browser's theme color from the new palette.
            updateAppearance(appearance);
        }, 'theme').then(() => {
            router.reload({ only: ['eventThemeChoice'] });
        });
    };

    return (
        <Select value={selected} onValueChange={select}>
            <SelectTrigger aria-label="Event theme" className="w-44">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {EVENT_THEME_CHOICES.map(({ value, label }) => (
                    <SelectItem key={value} value={value}>
                        {label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
