import { useLang } from '@erag/lang-sync-inertia/react';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { setCookie } from '@/hooks/use-appearance';

/**
 * The language is kept in a cookie, like the theme, and the page reloads so the server renders it in that language.
 */
export default function LanguageSelect() {
    const { locale, locales } = usePage().props;
    const { __ } = useLang();
    const [selected, setSelected] = useState(locale);

    const select = (value: string): void => {
        if (value === selected) {
            return;
        }

        setSelected(value);
        setCookie('locale', value);
        document.documentElement.lang = value.replace('_', '-');
        router.reload();
    };

    return (
        <Select value={selected} onValueChange={select}>
            <SelectTrigger
                className="w-56"
                aria-label={__('settings.language.title')}
                data-test="language-select"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {locales.map((option) => (
                    <SelectItem
                        key={option.value}
                        value={option.value}
                        lang={option.value.replace('_', '-')}
                    >
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
