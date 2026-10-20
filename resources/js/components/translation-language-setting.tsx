import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    Description,
    Field,
} from '@headlessui/react';
import { Form, router } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import UserTranslationLanguageController from '@/actions/App/Http/Controllers/UserTranslationLanguageController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { searchTranslationLanguages } from '@/lib/translation-languages';
import type { TranslationLanguage } from '@/lib/translation-languages';

export default function TranslationLanguageSetting({
    language,
    languages,
}: {
    language: string | null;
    languages: TranslationLanguage[];
}) {
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
        </section>
    );
}
