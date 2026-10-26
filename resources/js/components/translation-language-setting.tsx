import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    Description,
    Field,
} from '@headlessui/react';
import { Form, router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/UserAutomaticTranslationController';
import UserTranslationLanguageController from '@/actions/App/Http/Controllers/UserTranslationLanguageController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { searchTranslationLanguages } from '@/lib/translation-languages';
import type { TranslationLanguage } from '@/lib/translation-languages';
import { cn } from '@/lib/utils';

export default function TranslationLanguageSetting({
    language,
    languages,
}: {
    language: string | null;
    languages: TranslationLanguage[];
}) {
    const { auth } = usePage().props;
    const [savingAutomatic, setSavingAutomatic] = useState(false);
    const toggleAutomatic = () => {
        if (savingAutomatic) {
            return;
        }

        router.put(
            update.url(),
            { enabled: !auth.automaticTranslation },
            {
                preserveScroll: true,
                only: ['auth'],
                onStart: () => setSavingAutomatic(true),
                onFinish: () => setSavingAutomatic(false),
                onError: () =>
                    toast.error(
                        'Could not save automatic translation. Please try again.',
                    ),
                onSuccess: () => router.flushAll(),
            },
        );
    };
    const [selected, setSelected] = useState(language ?? '');
    const [query, setQuery] = useState('');
    const choices = [
        { code: '', name: 'Browser language (automatic)', nativeName: '' },
        ...languages,
    ];
    const filtered = searchTranslationLanguages(choices, query);

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Translation language"
                description="Choose the language for translated commits and replies, or follow your browser's language. Search by English or native name."
            />
            <Form
                {...UserTranslationLanguageController.update.form()}
                options={{ preserveScroll: true }}
                onSuccess={() => router.flushAll()}
            >
                {({ processing, errors }) => (
                    <Field>
                        <input
                            type="hidden"
                            name="translation_language"
                            value={selected}
                        />
                        <Combobox
                            value={selected}
                            onChange={(value: string | null) => {
                                if (value !== null) {
                                    setSelected(value);
                                }
                            }}
                            onClose={() => setQuery('')}
                        >
                            <div className="relative max-w-md">
                                <ComboboxInput
                                    autoComplete="off"
                                    autoCorrect="off"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    aria-label="Translation language"
                                    aria-invalid={Boolean(
                                        errors.translation_language,
                                    )}
                                    displayValue={(code: string) =>
                                        choices.find(
                                            (choice) => choice.code === code,
                                        )?.name ?? ''
                                    }
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    className="h-10 w-full min-w-0 rounded-md border border-input bg-background py-1 pr-9 pl-3 text-base text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                />
                                <ComboboxButton
                                    aria-label="Show translation languages"
                                    className="absolute inset-y-0 right-0 flex items-center px-2.5 text-muted-foreground"
                                >
                                    <ChevronsUpDown
                                        className="size-4"
                                        aria-hidden
                                    />
                                </ComboboxButton>
                            </div>
                            <ComboboxOptions
                                anchor="bottom start"
                                className="z-50 max-h-64 w-(--input-width) overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md [--anchor-gap:4px]"
                            >
                                {filtered.length === 0 && (
                                    <p
                                        className="px-2 py-1.5 text-sm wrap-anywhere text-muted-foreground"
                                        role="status"
                                    >
                                        No language matches "{query.trim()}".
                                    </p>
                                )}
                                {filtered.map(({ code, name, nativeName }) => (
                                    <ComboboxOption
                                        key={code}
                                        value={code}
                                        className="group flex cursor-default items-start gap-2 rounded-sm px-2 py-1.5 text-sm select-none data-focus:bg-accent data-focus:text-accent-foreground"
                                    >
                                        <Check
                                            className="mt-0.5 size-4 shrink-0 opacity-0 group-data-selected:opacity-100"
                                            aria-hidden
                                        />
                                        <span className="min-w-0">
                                            <span
                                                className="block truncate"
                                                title={name}
                                            >
                                                {name}
                                            </span>
                                            {nativeName !== '' &&
                                                nativeName !== name && (
                                                    <span
                                                        className="block truncate text-xs text-muted-foreground"
                                                        title={nativeName}
                                                    >
                                                        {nativeName}
                                                    </span>
                                                )}
                                        </span>
                                    </ComboboxOption>
                                ))}
                            </ComboboxOptions>
                        </Combobox>
                        <Description as="div" className="mt-2 min-h-5">
                            <InputError message={errors.translation_language} />
                        </Description>
                        <Button
                            type="submit"
                            className="mt-2"
                            disabled={
                                processing ||
                                query !== '' ||
                                selected === (language ?? '')
                            }
                            aria-busy={processing}
                        >
                            Save translation language
                        </Button>
                    </Field>
                )}
            </Form>
            <div className="mt-6 flex items-start justify-between gap-6 border-t pt-6">
                <div className="min-w-0 space-y-2">
                    <h3
                        id="automatic-translation-label"
                        className="text-sm font-medium"
                    >
                        Automatically show translations
                    </h3>
                    <p
                        id="automatic-translation-description"
                        className="text-sm text-muted-foreground"
                    >
                        Show available translations in your chosen language.
                        Turn off to read originals first and choose Translate
                        when you need it.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    aria-checked={auth.automaticTranslation}
                    aria-labelledby="automatic-translation-label"
                    aria-describedby="automatic-translation-description"
                    aria-disabled={savingAutomatic}
                    onClick={toggleAutomatic}
                    className={cn(
                        'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        auth.automaticTranslation
                            ? 'bg-primary'
                            : 'bg-muted-foreground/30',
                    )}
                >
                    <span
                        className={cn(
                            'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                            auth.automaticTranslation && 'translate-x-5',
                        )}
                    />
                </button>
            </div>
        </section>
    );
}
