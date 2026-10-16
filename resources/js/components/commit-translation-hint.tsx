import { Languages } from 'lucide-react';
import type { CommitTranslation } from '@/hooks/use-commit-translation';
import { languageName } from '@/lib/translation';

const buttonClassName = 'transition-colors hover:text-foreground';

/**
 * The line under a commit body that offers the browser's own translation, and
 * says what it did once it is done. It never appears where the browser has no
 * translation to offer.
 */
export function CommitTranslationHint({
    translation,
}: {
    translation: CommitTranslation;
}) {
    const { isTranslated, language, phase, progress, target } = translation;
    const written = languageName(language ?? target);

    return (
        <p
            aria-live="polite"
            className="mt-1 flex w-fit items-center gap-1.5 font-mono text-xs text-muted-foreground"
            data-test="commit-translation"
        >
            {phase === 'idle' && (
                <button
                    type="button"
                    onClick={translation.translate}
                    title={`Translate to ${languageName(target)} on your device`}
                    className={`flex items-center gap-1.5 ${buttonClassName}`}
                    data-test="translate-commit-button"
                >
                    <Languages className="size-3.5" />
                    Translate
                </button>
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
