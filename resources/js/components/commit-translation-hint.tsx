import { ChevronDown, Languages } from 'lucide-react';
import type { CommitTranslation } from '@/hooks/use-commit-translation';
import { languageName } from '@/lib/translation';

const buttonClassName = 'transition-colors hover:text-foreground';

/** A language the reader picks, in the quiet mono line of the hint. */
function LanguageSelect({
    languages,
    value,
    onChange,
    label,
    testId,
}: {
    languages: string[];
    value: string;
    onChange: (language: string) => void;
    label: string;
    testId: string;
}) {
    return (
        <span className="relative inline-flex items-center">
            <select
                value={value}
                onChange={(event) => onChange(event.currentTarget.value)}
                aria-label={label}
                className="cursor-pointer appearance-none bg-transparent pr-3.5 text-foreground outline-none"
                data-test={testId}
            >
                {languages.map((tag) => (
                    <option key={tag} value={tag}>
                        {languageName(tag)}
                    </option>
                ))}
            </select>
            <ChevronDown
                aria-hidden="true"
                className="pointer-events-none absolute right-0 size-3.5"
            />
        </span>
    );
}

/**
 * The line under a commit body that offers the browser's own translation, and
 * says what it did once it is done. It never appears where the browser has no
 * translation to offer. A browser that translates without detecting the
 * language lets the reader pick the two languages instead.
 */
export function CommitTranslationHint({
    translation,
}: {
    translation: CommitTranslation;
}) {
    const {
        isTranslated,
        language,
        languages,
        phase,
        progress,
        source,
        target,
    } = translation;
    const written = languageName(language ?? target);
    const hasPickers = languages !== null;

    return (
        <p
            aria-live="polite"
            className="mt-1 flex w-fit items-center gap-1.5 font-mono text-xs text-muted-foreground"
            data-test="commit-translation"
        >
            {phase === 'idle' && (
                <>
                    <button
                        type="button"
                        onClick={translation.translate}
                        title={
                            hasPickers
                                ? `Translate from ${languageName(source)} to ${languageName(target)} on your device`
                                : `Translate to ${languageName(target)} on your device`
                        }
                        className={`flex items-center gap-1.5 ${buttonClassName}`}
                        data-test="translate-commit-button"
                    >
                        <Languages className="size-3.5" />
                        Translate
                    </button>

                    {languages !== null && (
                        <>
                            <span>from</span>
                            <LanguageSelect
                                languages={languages}
                                value={source}
                                onChange={translation.setSource}
                                label="Translate from"
                                testId="translate-from-language"
                            />
                            <span>to</span>
                            <LanguageSelect
                                languages={languages}
                                value={target}
                                onChange={translation.setTarget}
                                label="Translate to"
                                testId="translate-to-language"
                            />
                        </>
                    )}
                </>
            )}

            {phase === 'working' && (
                <span>
                    {progress === null
                        ? 'Translating…'
                        : `Downloading the language model… ${Math.round(progress * 100)}%`}
                </span>
            )}

            {phase === 'translated' && (
                <>
                    <span>
                        {isTranslated
                            ? `Translated from ${written} on your device`
                            : `Written in ${written}`}
                    </span>
                    <span aria-hidden>·</span>
                    <button
                        type="button"
                        onClick={
                            isTranslated
                                ? translation.showOriginal
                                : translation.showTranslation
                        }
                        className={buttonClassName}
                        data-test="toggle-commit-translation"
                    >
                        {isTranslated ? 'See original' : 'Show translation'}
                    </button>
                </>
            )}

            {phase === 'same-language' && <span>Already in {written}</span>}

            {phase === 'unknown-language' && (
                <span>Could not tell the language</span>
            )}

            {phase === 'failed' && (
                <>
                    <span>Translation failed</span>
                    <span aria-hidden>·</span>
                    <button
                        type="button"
                        onClick={translation.translate}
                        className={buttonClassName}
                        data-test="retry-commit-translation"
                    >
                        Retry
                    </button>
                </>
            )}
        </p>
    );
}
