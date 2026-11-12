import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserEventThemeController';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppearance } from '@/hooks/use-appearance';
import { usePicked } from '@/hooks/use-picked';
import type { EventThemeChoice } from '@/lib/event-theme';
import { EVENT_THEME_CHOICES, setEventThemeChoice } from '@/lib/event-theme';
import { withViewTransition } from '@/lib/utils';

export default function EventThemeSelect() {
    const { eventThemeChoice } = usePage().props;
    const { appearance, updateAppearance } = useAppearance();
    const { picked, pick } = usePicked<string>();
    const selected = picked ?? eventThemeChoice;

    const show = (value: string): Promise<void> =>
        withViewTransition(() => {
            setEventThemeChoice(value as EventThemeChoice);
            // Re-applies the appearance, which refreshes the browser's theme color from the new palette.
            updateAppearance(appearance);
        }, 'theme');

    const select = (value: string): void => {
        if (value === selected) {
            return;
        }

        const previous = selected;
        const done = pick(value);
        const rollBack = () => {
            void show(previous);
            toast.error('Could not save the event theme. Please try again.');
        };

        // Saved on the account too, so the user's other browsers and apps follow it.
        void show(value).then(() => {
            router.put(
                update.url(),
                { choice: value },
                {
                    preserveScroll: true,
                    only: ['eventTheme', 'eventThemeChoice'],
                    onFinish: done,
                    onError: rollBack,
                    onHttpException: rollBack,
                    onNetworkError: rollBack,
                },
            );
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
