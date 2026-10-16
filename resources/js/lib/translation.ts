import type { BodySegment } from '@/types';
import type {
    TranslationMonitor,
    TranslatorSession,
} from '@/types/translation';

export type TranslationStatus =
    | 'unsupported'
    | 'unknown-language'
    | 'same-language'
    | 'translated';

export type TranslationOutcome = {
    status: TranslationStatus;
    /** Language the text is written in, as a base tag: "fr" for "fr-FR". */
    language: string | null;
    /** The text segments, translated when the status is "translated". */
    texts: string[];
};

/** Report the download of a model, from 0 to 1. */
export type ProgressListener = (progress: number) => void;

/** Below this, a detection is a guess: "nice" is not worth translating. */
const minConfidence = 0.5;

/** The translation sessions already created, so a model is loaded once. */
const sessions = new Map<string, Promise<TranslatorSession>>();

export function isTranslationSupported(): boolean {
    return (
        globalThis.Translator !== undefined &&
        globalThis.LanguageDetector !== undefined
    );
}

/** The language the reader's browser is in, as a base tag: "fr" for "fr-FR". */
export function browserLanguage(): string {
    const language = typeof navigator === 'undefined' ? '' : navigator.language;

    return language === '' ? 'en' : baseLanguage(language);
}

export function baseLanguage(tag: string): string {
    return tag.toLowerCase().split('-')[0];
}

/** The name of a language in the reader's language: "French" for "fr". */
export function languageName(
    tag: string,
    inLanguage: string = browserLanguage(),
): string {
    try {
        return (
            new Intl.DisplayNames([inLanguage], { type: 'language' }).of(tag) ??
            tag
        );
    } catch {
        return tag;
    }
}

function downloadMonitor(
    onProgress: ProgressListener | undefined,
): TranslationMonitor | undefined {
    if (onProgress === undefined) {
        return undefined;
    }

    return (monitor: EventTarget) => {
        monitor.addEventListener('downloadprogress', (event) => {
            const { loaded } = event as Event & { loaded?: number };

            if (loaded !== undefined) {
                onProgress(Math.min(1, Math.max(0, loaded)));
            }
        });
    };
}

/**
 * The language a text is written in, or null when the browser can't tell.
 */
export async function detectLanguage(
    text: string,
    onProgress?: ProgressListener,
): Promise<string | null> {
    const detector = await globalThis.LanguageDetector?.create({
        monitor: downloadMonitor(onProgress),
    });

    if (detector === undefined) {
        return null;
    }

    try {
        const detection = (await detector.detect(text)).at(0);

        return detection !== undefined && detection.confidence >= minConfidence
            ? baseLanguage(detection.detectedLanguage)
            : null;
    } finally {
        detector.destroy();
    }
}

async function translatorSession(
    source: string,
    target: string,
    onProgress: ProgressListener | undefined,
): Promise<TranslatorSession> {
    const key = `${source}->${target}`;
    const session = sessions.get(key);

    if (session !== undefined) {
        return session;
    }

    const translator = globalThis.Translator;

    if (translator === undefined) {
        throw new Error('This browser has no Translator API.');
    }

    const created = translator
        .create({
            sourceLanguage: source,
            targetLanguage: target,
            monitor: downloadMonitor(onProgress),
        })
        .catch((error: unknown) => {
            sessions.delete(key);

            throw error;
        });

    sessions.set(key, created);

    return created;
}

/** The text of the segments a translation may change: code stays as written. */
export function translatableTexts(segments: BodySegment[]): string[] {
    return segments.flatMap((segment) =>
        segment.type === 'text' ? [segment.text] : [],
    );
}

/** The same segments with their text replaced, code left untouched. */
export function replaceTexts(
    segments: BodySegment[],
    texts: string[],
): BodySegment[] {
    let index = 0;

    return segments.map((segment) => {
        if (segment.type !== 'text') {
            return segment;
        }

        const text = texts.at(index);
        index += 1;

        return text === undefined ? segment : { type: 'text', text };
    });
}

/**
 * Detect the language of a commit body and translate it, on the device, when it
 * is not already in the language of the reader.
 */
export async function translateBody(
    segments: BodySegment[],
    target: string,
    onProgress?: ProgressListener,
): Promise<TranslationOutcome> {
    const texts = translatableTexts(segments);
    const body = texts.join('\n').trim();

    if (!isTranslationSupported()) {
        return { status: 'unsupported', language: null, texts };
    }

    const language =
        body === '' ? null : await detectLanguage(body, onProgress);

    if (language === null) {
        return { status: 'unknown-language', language: null, texts };
    }

    if (language === target) {
        return { status: 'same-language', language, texts };
    }

    const translator = await translatorSession(language, target, onProgress);

    return {
        status: 'translated',
        language,
        texts: await Promise.all(
            texts.map((text) => translator.translate(text)),
        ),
    };
}
