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
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useState } from 'react';
import UserKnownLanguagesController from '@/actions/App/Http/Controllers/UserKnownLanguagesController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { searchTranslationLanguages } from '@/lib/translation-languages';
import type { TranslationLanguage } from '@/lib/translation-languages';

export default function KnownLanguagesSetting({
    languages,
    choices,
}: {
    languages: string[];
    choices: TranslationLanguage[];
}) {
    const [selected, setSelected] = useState(languages);
    const [query, setQuery] = useState('');
    const filtered = searchTranslationLanguages(choices, query);
    const unchanged =
        selected.length === languages.length &&
        selected.every((code) => languages.includes(code));
    const nameOf = (code: string) =>
        choices.find((choice) => choice.code === code)?.name ?? code;
    const errorOf = (errors: Record<string, string>) =>
        Object.entries(errors).find(([key]) =>
            key.startsWith('known_languages'),
        )?.[1];

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Languages you read"
                description="Commits in these languages are never translated for you, even when a translation already exists. Search by English or native name."
            />
            <Form
                {...UserKnownLanguagesController.update.form()}
                options={{ preserveScroll: true }}
                onSuccess={() => router.flushAll()}
            >
                {({ processing, errors }) => (
                    <Field>
                        {selected.length === 0 ? (
                            <input
                                type="hidden"
                                name="known_languages"
                                value=""
                            />
                        ) : (
                            selected.map((code) => (
                                <input
                                    key={code}
                                    type="hidden"
                                    name="known_languages[]"
                                    value={code}
                                />
                            ))
                        )}
                        <Combobox
                            multiple
                            value={selected}
                            onChange={(value: string[]) => setSelected(value)}
                            onClose={() => setQuery('')}
                        >
                            <div className="relative max-w-md">
                                <ComboboxInput
                                    autoComplete="off"
                                    autoCorrect="off"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    aria-label="Languages you read"
                                    aria-invalid={Boolean(errorOf(errors))}
                                    placeholder="Add a language"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    className="h-10 w-full min-w-0 rounded-md border border-input bg-background py-1 pr-9 pl-3 text-base text-foreground shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                />
                                <ComboboxButton
                                    aria-label="Show languages"
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
                        <ul
                            className="mt-2 flex min-h-7 max-w-md flex-wrap gap-2"
                            aria-label="Selected languages"
                        >
                            {selected.map((code) => (
                                <li
                                    key={code}
                                    className="flex h-7 items-center gap-1 rounded-md border bg-muted px-2 text-sm"
                                >
                                    <span
                                        className="truncate"
                                        title={nameOf(code)}
                                    >
                                        {nameOf(code)}
                                    </span>
                                    <button
                                        type="button"
                                        aria-label={`Remove ${nameOf(code)}`}
                                        onClick={() =>
                                            setSelected(
                                                selected.filter(
                                                    (known) => known !== code,
                                                ),
                                            )
                                        }
                                        className="rounded-sm text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        <X className="size-3.5" aria-hidden />
                                    </button>
                                </li>
                            ))}
                        </ul>
                        <Description as="div" className="mt-2 min-h-5">
                            <InputError message={errorOf(errors)} />
                        </Description>
                        <Button
                            type="submit"
                            className="mt-2"
                            disabled={processing || query !== '' || unchanged}
                            aria-busy={processing}
                        >
                            Save languages
                        </Button>
                    </Field>
                )}
            </Form>
        </section>
    );
}
