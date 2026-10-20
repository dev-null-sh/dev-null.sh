import { Form, router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import UserTranslationLanguageController from '@/actions/App/Http/Controllers/UserTranslationLanguageController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

export default function TranslationLanguageSetting({
    language,
    languages,
}: {
    language: string | null;
    languages: { code: string; name: string }[];
}) {
    const [selected, setSelected] = useState(language ?? '');

    return (
        <section className="px-4 py-6 sm:px-6">
            <Heading
                title="Translation language"
                description="Choose the language for translated commits and replies, or follow your browser's language."
            />
            <Form
                {...UserTranslationLanguageController.update.form()}
                options={{ preserveScroll: true }}
                onSuccess={() => router.flushAll()}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="relative max-w-md">
                            <select
                                aria-label="Translation language"
                                aria-describedby="translation-language-error"
                                aria-invalid={Boolean(
                                    errors.translation_language,
                                )}
                                name="translation_language"
                                value={selected}
                                onChange={(event) =>
                                    setSelected(event.target.value)
                                }
                                className="h-10 w-full min-w-0 appearance-none truncate rounded-md border border-input bg-background py-1 pr-9 pl-3 text-base text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                            >
                                <option value="">
                                    Browser language (automatic)
                                </option>
                                {languages.map(({ code, name }) => (
                                    <option key={code} value={code}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                            <ChevronDown
                                aria-hidden
                                className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>
                        <div
                            id="translation-language-error"
                            className="mt-2 min-h-5"
                        >
                            <InputError message={errors.translation_language} />
                        </div>
                        <Button
                            type="submit"
                            className="mt-2"
                            disabled={
                                processing || selected === (language ?? '')
                            }
                            aria-busy={processing}
                        >
                            Save translation language
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
