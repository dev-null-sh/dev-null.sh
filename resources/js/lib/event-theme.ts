import { setCookie } from '@/hooks/use-appearance';

export type EventThemeChoice =
    | 'auto'
    | 'none'
    | 'halloween'
    | 'christmas'
    | 'newyear';

export const EVENT_THEME_CHOICES: readonly {
    value: EventThemeChoice;
    label: string;
}[] = [
    { value: 'auto', label: 'Auto' },
    { value: 'none', label: 'Default' },
    { value: 'halloween', label: 'Halloween' },
    { value: 'christmas', label: 'Christmas' },
    { value: 'newyear', label: 'New Year' },
];

/**
 * The server renders the theme the reader's choice calls for in data-event-theme, and what auto means today in
 * data-auto-event-theme. Switching the choice here follows the same rule without a reload.
 */
export function setEventThemeChoice(choice: EventThemeChoice): void {
    const root = document.documentElement;
    const theme =
        choice === 'auto'
            ? root.dataset.autoEventTheme
            : choice === 'none'
              ? undefined
              : choice;

    if (theme === undefined) {
        delete root.dataset.eventTheme;
    } else {
        root.dataset.eventTheme = theme;
    }

    setCookie('event_theme', choice);
}
