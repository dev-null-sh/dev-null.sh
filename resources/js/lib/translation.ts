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
    return globalThis.Translator !== undefined;
}

/**
 * What this browser can do with a commit body. The APIs are not enough: a
 * browser that ships them can still have no language detection model for the
 * device, and then the reader picks the languages instead.
 */
export type TranslationMode =
    /** Nobody asked the browser yet. */
    | 'unknown'
    /** No translation at all: no control is shown. */
    | 'unsupported'
    /** The browser tells what language a text is written in. */
    | 'automatic'
    /** The browser translates, but not detects: the reader picks the languages. */
    | 'manual';

/**
 * The languages offered when the browser cannot detect one. This is only a
 * starting point: each one is checked against the browser before it is shown.
 */
const languageCandidates = [
    'af',
    'ar',
    'bg',
    'bn',
    'ca',
    'cs',
    'da',
    'de',
    'el',
    'en',
    'es',
    'et',
    'fa',
    'fi',
    'fr',
    'he',
    'hi',
    'hr',
    'hu',
    'id',
    'it',
    'ja',
    'ko',
    'lt',
    'lv',
    'ms',
    'nl',
    'no',
    'pl',
    'pt',
    'ro',
    'ru',
    'sk',
    'sl',
    'sr',
    'sv',
    'sw',
    'ta',
    'th',
    'tr',
    'uk',
    'ur',
    'vi',
    'zh',
];

const listeners = new Set<() => void>();

/** The languages the browser offers, once they have been asked for. */
const probes = new Map<string, Promise<string[]>>();

let mode: TranslationMode = 'unknown';
let languages: string[] | null = null;

function publish(
    nextMode: TranslationMode,
    nextLanguages: string[] | null = null,
): TranslationMode {
    const changed = mode !== nextMode || languages !== nextLanguages;

    mode = nextMode;
    languages = nextLanguages;

    if (changed) {
        for (const listener of listeners) {
            listener();
        }
    }

    return nextMode;
}

export function subscribeTranslation(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function translationMode(): TranslationMode {
    return mode;
}

/** The languages to pick from, or null while the browser detects them itself. */
export function translationLanguages(): string[] | null {
    return languages;
}

/**
 * Ask the browser what it can do. It is cheap and downloads nothing, so it runs
 * again on every call rather than being cached.
 */
export function checkTranslation(): Promise<TranslationMode> {
    if (!isTranslationSupported()) {
        return Promise.resolve(publish('unsupported'));
    }

    const detector = globalThis.LanguageDetector;

    if (detector === undefined) {
        return offerLanguages();
    }

    return detector
        .availability()
        .then((state) =>
            state === 'unavailable' ? offerLanguages() : publish('automatic'),
        )
        .catch(() => offerLanguages());
}

/**
 * Check every language the reader could pick. The result depends only on the
 * language they read, so one round is shared by the whole page.
 */
function offerLanguages(): Promise<TranslationMode> {
    const target = browserLanguage();
    let probe = probes.get(target);

    if (probe === undefined) {
        probe = probeLanguages(target);
        probes.set(target, probe);
    }

    return probe.then((found) =>
        publish(found.length === 0 ? 'unsupported' : 'manual', found),
    );
}

async function probeLanguages(target: string): Promise<string[]> {
    const translator = globalThis.Translator;

    if (translator === undefined) {
        return [];
    }

    const checked = await Promise.all(
        languageCandidates.map(async (tag) => {
            // A language is never paired with itself, so the reader's own
            // language is checked the other way around.
            const pair =
                tag === target
                    ? { sourceLanguage: 'en', targetLanguage: tag }
                    : { sourceLanguage: tag, targetLanguage: target };

            if (pair.sourceLanguage === pair.targetLanguage) {
                return tag;
            }

            try {
                const state = await translator.availability(pair);

                return state === 'unavailable' ? null : tag;
            } catch {
                return null;
            }
        }),
    );

    const found = checked.filter((tag): tag is string => tag !== null);
    const collator = new Intl.Collator(browserLanguage());

    return found.sort((left, right) =>
        collator.compare(languageName(left), languageName(right)),
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

/**
 * A translator trims what it returns. The space around an inline code span lives
 * at the edge of the segments next to it, so it is put back: without it, "un
 * `SELECT` par page" reads "aSELECTper page".
 */
function keepSurroundingSpace(original: string, translated: string): string {
    if (original.trim() === '') {
        return original;
    }

    const leading = original.slice(
        0,
        original.length - original.trimStart().length,
    );
    const trailing = original.slice(original.trimEnd().length);

    return `${leading}${translated.trim()}${trailing}`;
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

export type TranslationOptions = {
    /** Report the download of a model, from 0 to 1. */
    onProgress?: ProgressListener;
    /** The language the text is written in, when the browser cannot tell. */
    source?: string;
};

/**
 * Detect the language of a commit body and translate it, on the device, when it
 * is not already in the language of the reader.
 */
export async function translateBody(
    segments: BodySegment[],
    target: string,
    options: TranslationOptions = {},
): Promise<TranslationOutcome> {
    const { onProgress, source } = options;
    const texts = translatableTexts(segments);
    const body = texts.join('\n').trim();

    if (!isTranslationSupported()) {
        return { status: 'unsupported', language: null, texts };
    }

    const language =
        source ?? (body === '' ? null : await detectLanguage(body, onProgress));

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
            texts.map(async (text) =>
                keepSurroundingSpace(text, await translator.translate(text)),
            ),
        ),
    };
}
