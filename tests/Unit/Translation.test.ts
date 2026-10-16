/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    baseLanguage,
    checkTranslation,
    languageName,
    replaceTexts,
    translateBody,
    translatableTexts,
    translationLanguages,
    translationMode,
} from '../../resources/js/lib/translation.ts';
import type { BodySegment } from '../../resources/js/types';
import type {
    LanguageDetection,
    LanguageDetectorApi,
    ModelAvailability,
    TranslationMonitor,
    TranslatorApi,
} from '../../resources/js/types/translation.ts';

const segments: BodySegment[] = [
    { type: 'text', text: 'Bonjour le monde' },
    { type: 'code', code: 'php artisan migrate' },
];

const french: LanguageDetection[] = [
    { detectedLanguage: 'fr-FR', confidence: 0.92 },
];

/** The languages the stubbed browser can translate between. */
const languages = ['fr', 'en', 'de', 'es', 'it', 'pt'];

type Stub = {
    /** What the language detector reports for a text. */
    detections?: LanguageDetection[];
    /** What the translator returns for a text. It trims, like a real one. */
    translate?: (text: string) => string;
    /** What the browser reports while it downloads a model. */
    downloads?: TranslationMonitor;
    /** What the language detector says about its own model. */
    detector?: ModelAvailability;
};

/**
 * Answer every detection and translation with the given results, and record the
 * language pairs a session was created for.
 */
function stubBrowser({
    detections = french,
    translate = (text) => text.trim().toUpperCase(),
    downloads = () => undefined,
    detector = 'available',
}: Stub = {}): { pairs: string[] } {
    const pairs: string[] = [];
    const reportDownload = (monitor: TranslationMonitor | undefined) => {
        if (monitor === undefined) {
            return;
        }

        const target = new EventTarget();
        monitor(target);
        downloads(target);
    };

    const languageDetector: LanguageDetectorApi = {
        availability: () => Promise.resolve(detector),
        create: (options) => {
            reportDownload(options?.monitor);

            return Promise.resolve({
                detect: () => Promise.resolve(detections),
                destroy: () => undefined,
            });
        },
    };

    const translator: TranslatorApi = {
        availability: ({ sourceLanguage, targetLanguage }) =>
            Promise.resolve(
                languages.includes(sourceLanguage) &&
                    languages.includes(targetLanguage)
                    ? 'downloadable'
                    : 'unavailable',
            ),
        create: (options) => {
            pairs.push(`${options.sourceLanguage}->${options.targetLanguage}`);
            reportDownload(options.monitor);

            return Promise.resolve({
                translate: (text: string) => Promise.resolve(translate(text)),
                destroy: () => undefined,
            });
        },
    };

    globalThis.LanguageDetector = languageDetector;
    globalThis.Translator = translator;

    return { pairs };
}

function clearBrowser(): void {
    globalThis.LanguageDetector = undefined;
    globalThis.Translator = undefined;
}

void test('reads a base language out of a locale and names it', () => {
    assert.equal(baseLanguage('fr-FR'), 'fr');
    assert.equal(baseLanguage('EN'), 'en');
    assert.equal(languageName('fr', 'en'), 'French');
    assert.equal(languageName('de', 'en'), 'German');
});

void test('translates text segments and leaves code as written', () => {
    assert.deepEqual(translatableTexts(segments), ['Bonjour le monde']);
    assert.deepEqual(replaceTexts(segments, ['Hello world']), [
        { type: 'text', text: 'Hello world' },
        { type: 'code', code: 'php artisan migrate' },
    ]);
});

void test('keeps a segment when the translation is missing', () => {
    assert.deepEqual(replaceTexts(segments, []), segments);
});

void test('keeps the spaces around an inline code span', async (t) => {
    t.after(clearBrowser);
    // Its own language pair: a session is reused once a pair has been created.
    stubBrowser({ detections: [{ detectedLanguage: 'it', confidence: 0.9 }] });

    const outcome = await translateBody(
        [
            { type: 'text', text: 'un ' },
            { type: 'code', code: 'SELECT' },
            { type: 'text', text: ' par page' },
        ],
        'en',
    );

    assert.deepEqual(outcome.texts, ['UN ', ' PAR PAGE']);
});

void test('translates a body written in another language', async (t) => {
    t.after(clearBrowser);
    const { pairs } = stubBrowser();

    const outcome = await translateBody(segments, 'en');

    assert.equal(outcome.status, 'translated');
    assert.equal(outcome.language, 'fr');
    assert.deepEqual(outcome.texts, ['BONJOUR LE MONDE']);
    assert.deepEqual(pairs, ['fr->en']);
});

void test('translates from the language the reader picked', async (t) => {
    t.after(clearBrowser);
    const { pairs } = stubBrowser({ detections: [] });

    const outcome = await translateBody(segments, 'en', { source: 'de' });

    assert.equal(outcome.status, 'translated');
    assert.equal(outcome.language, 'de');
    assert.deepEqual(outcome.texts, ['BONJOUR LE MONDE']);
    assert.deepEqual(pairs, ['de->en']);
});

void test('creates one session per language pair', async (t) => {
    t.after(clearBrowser);
    // Its own language pair: a session is reused once a pair has been created.
    const { pairs } = stubBrowser({
        detections: [{ detectedLanguage: 'pt', confidence: 0.8 }],
    });

    await translateBody(segments, 'en');
    await translateBody(segments, 'en');

    assert.deepEqual(pairs, ['pt->en']);
});

void test('reports the model downloads as they go', async (t) => {
    t.after(clearBrowser);
    const progress: number[] = [];

    stubBrowser({
        detections: [{ detectedLanguage: 'es', confidence: 0.8 }],
        downloads: (monitor) => {
            for (const loaded of [0.25, 0.75]) {
                monitor.dispatchEvent(
                    Object.assign(new Event('downloadprogress'), { loaded }),
                );
            }
        },
    });

    await translateBody(segments, 'en', {
        onProgress: (value) => progress.push(value),
    });

    // The detection model and the translation model report through one listener.
    assert.deepEqual(progress, [0.25, 0.75, 0.25, 0.75]);
});

void test('leaves a body already in the reader language alone', async (t) => {
    t.after(clearBrowser);
    const { pairs } = stubBrowser({
        detections: [{ detectedLanguage: 'en', confidence: 0.9 }],
    });

    const outcome = await translateBody(segments, 'en');

    assert.equal(outcome.status, 'same-language');
    assert.equal(outcome.language, 'en');
    assert.deepEqual(outcome.texts, ['Bonjour le monde']);
    assert.deepEqual(pairs, []);
});

void test('does not guess a language the browser is unsure about', async (t) => {
    t.after(clearBrowser);
    const { pairs } = stubBrowser({
        detections: [{ detectedLanguage: 'fr', confidence: 0.3 }],
    });

    const outcome = await translateBody(segments, 'en');

    assert.equal(outcome.status, 'unknown-language');
    assert.equal(outcome.language, null);
    assert.deepEqual(outcome.texts, ['Bonjour le monde']);
    assert.deepEqual(pairs, []);
});

void test('reports a browser without the APIs', async (t) => {
    t.after(clearBrowser);
    clearBrowser();

    const outcome = await translateBody(segments, 'en');

    assert.equal(outcome.status, 'unsupported');
    assert.equal(outcome.language, null);
});

void test('a browser that detects the language translates on its own', async (t) => {
    t.after(clearBrowser);
    stubBrowser();

    assert.equal(await checkTranslation(), 'automatic');
    assert.equal(translationMode(), 'automatic');
    assert.equal(translationLanguages(), null);
});

void test('a browser with no detection model offers the languages it knows', async (t) => {
    t.after(clearBrowser);
    stubBrowser({ detector: 'unavailable' });

    assert.equal(await checkTranslation(), 'manual');
    assert.equal(translationMode(), 'manual');
    // The reader's own language is checked the other way around, and the list
    // is sorted by the name the reader sees: English, French, German, Italian,
    // Portuguese, Spanish.
    assert.deepEqual(translationLanguages(), [
        'en',
        'fr',
        'de',
        'it',
        'pt',
        'es',
    ]);
});

void test('a browser that cannot translate at all offers nothing', async (t) => {
    t.after(clearBrowser);
    clearBrowser();

    assert.equal(await checkTranslation(), 'unsupported');
    assert.equal(translationMode(), 'unsupported');
    assert.equal(translationLanguages(), null);
});
